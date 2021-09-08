<?php

require_once __DIR__  . '/sonarrApi.class.php';
require_once __DIR__  . '/sonarrUtils.class.php';
require_once __DIR__  . '/Utils/LogSonarr.php';
require_once __DIR__  . '/Utils/SonarrRadarrUtils.php';


class sonarrApiWrapper
{
    protected $sonarrApi;
    protected $utils;

    public function __construct($url, $apiKey)
    {
        if ($url == NULL || $url == "") {
            LogSonarr::error('No URL given, this plugin needs the URL to your Sonarr to work');
        }
        if ($apiKey == NULL || $apiKey == "") {
            LogSonarr::error('No API KEY given, this plugin needs the API KEY of your Sonarr to work');
        }
        $this->sonarrApi = new sonarrApi($url, $apiKey);
        $this->utils = new sonarrUtils();
    }

    public function refreshSonarr($context)
    {
        LogSonarr::info('start REFRESH SONARR');
        $separator = $context->getSeparator();
        LogSonarr::info('selected separator: ' . $separator);
        $formattor = $context->getConfiguration('formattorEpisode');
        LogSonarr::info('selected formattor: ' . $formattor);
        LogSonarr::info('getting futures episodes, will look for selected rule');
        $futurEpisodesRules = $context->getConfigurationFor($context, "dayFutureEpisodes", "maxFutureEpisodes");
        $this->getFutureEpisodesFormattedList($context, $separator, $futurEpisodesRules, $formattor);
        LogSonarr::info('getting missings episodes, will look for selected rule');
        $missingEpisodesRules = $context->getConfigurationFor($context, "dayMissingEpisodes", "maxMissingEpisodes");
        $this->getMissingEpisodesFormattedList($context, $missingEpisodesRules, $separator, $formattor);
        LogSonarr::info('getting last downloaded episodes, will look for specific rules');
        $groupEpisode = $context->getConfiguration('groupedEpisodes');
        $separatorEpisodes = $this->getSeparatorEpisodes($context);
        $downloadedEpisodesRules = $context->getConfigurationFor($context, "dayDownloadedEpisodes", "maxDownloadedEpisodes");
        $this->getDownladedEpisodesFormattedList($context, $downloadedEpisodesRules, $separator, $formattor, $groupEpisode, $separatorEpisodes);
        LogSonarr::info('notify for last downloaded episodes');
        $last_refresh_date = $context->getCmd(null, 'last_episode')->getValueDate();
        $this->notifyEpisode('sonarr', $last_refresh_date, $context, $formattor, $groupEpisode, $separatorEpisodes);
        LogSonarr::info('getting the monitored series');
        $liste_monitored_series = $this->getMonitoredSeries($separator);
        if ($liste_monitored_series == "") {
            LogSonarr::info('no monitored series');
        } else {
            $context->checkAndUpdateCmd('monitoredSeries', $liste_monitored_series);
        }
        LogSonarr::info('stop REFRESH SONARR');
    }
    public function getSeparatorEpisodes($context)
    {
        $separator = $context->getConfiguration('separatorEpisodes');
        if ($separator != NULL) {
            return $separator;
        } else {
            return ", ";
        }
    }

    public function getFutureEpisodesFormattedList($context, $separator, $rules, $formattor)
    {
        $futurEpisodesList = $this->getFutureEpisodesArray($rules, $formattor);
        // Save RAW
        $context->checkAndUpdateCmd('day_episodes_raw', json_encode($futurEpisodesList));
        // Format list
        $futurEpisodesListStr = '';
        foreach ($futurEpisodesList as $futurEpisode) {
            $futurEpisodesListStr = $this->utils->formatList($futurEpisodesListStr, $futurEpisode["title"], $separator);
        }
        if ($futurEpisodesListStr == "") {
            LogSonarr::info('no future episodes');
        }
        // Save formatted list
        $context->checkAndUpdateCmd('day_episodes', $futurEpisodesListStr);
    }
    private function getFutureEpisodesArray($rules, $formattor)
    {
        $liste_episode = [];
        $dayFutures = $rules["numberDays"];
        $currentDate = new DateTime();
        $futureDate = new DateTime();
        $futureDate->add(new DateInterval('P' . $dayFutures . 'D'));
        $currentDate = $currentDate->format('Y-m-d');
        $futureDate = $futureDate->format('Y-m-d');
        // Server call
        LogSonarr::debug('fetching futures episodes between ' . $currentDate . ' and ' . $futureDate);
        $calendar = $this->sonarrApi->getCalendar($currentDate, $futureDate);
        LogSonarr::debug('JSON FOR CALENDAR' . $calendar);
        // return error if needed
        $calendar = $this->utils->verifyJson($calendar);
        if ($calendar == NULL) {
            return "";
        }
        $calendar = $this->utils->applyMaxRulesToArray($calendar, $rules);
        // Analyze datas
        foreach ($calendar as $serie) {
            $episodeTitle = $serie["series"]["title"];
            $seasonNumber = $serie["seasonNumber"];
            $episodeNumber = $serie["episodeNumber"];
            $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber, $formattor);
            $seriesId = $serie["seriesId"];
            $ddl_date_str = $serie["airDateUtc"];
            $ddl_date_str = $this->utils->formatDate($ddl_date_str);
            $images = $serie["series"]["images"];
            $urlImage = "";
            foreach ($images as $image) {
                if ($image["coverType"] == "poster") {
                    $urlImage =  $image["url"];
                }
            }
            //Save image
            $this->saveImage($urlImage, $seriesId);
            $downloaded = $serie["hasFile"];
            $size = $serie["episodeFile"]["size"];
            if ($size != null && $size != 0) {
                $size = $this->utils->formatSize($size);
            } else {
                $size = "";
            }
            $quality = $serie["episodeFile"]["quality"]["quality"]["resolution"];
            if ($quality != null && $quality != 0) {
                $quality = $quality . "p";
            } else {
                $quality = "";
            }
            $episodeImage = array(
                'title' => $episode,
                'serie' => $episodeTitle,
                'image' => $urlImage,
                'seriesId' => $seriesId,
                'date' => $ddl_date_str,
                'downloaded' => $downloaded,
                'size' => $size,
                'quality' => $quality,
            );
            array_push($liste_episode, $episodeImage);
        }
        return $liste_episode;
    }
    public function getMissingEpisodesFormattedList($context, $rules, $separator, $formattor)
    {
        $missingEpisodesList = $this->getMissingEpisodesArray($rules, $formattor);
        // Save RAW
        $context->checkAndUpdateCmd('day_missing_episodes_raw', json_encode($missingEpisodesList));
        // Format list
        $missingEpisodesListStr = "";
        foreach ($missingEpisodesList as $missingEpisode) {
            $missingEpisodesListStr = $this->utils->formatList($missingEpisodesListStr, $missingEpisode["title"], $separator);
        }
        if ($missingEpisodesListStr == "") {
            LogSonarr::info('no missing episodes');
        }
        // Save format
        $context->checkAndUpdateCmd('day_missing_episodes', $missingEpisodesListStr);
    }
    public function getMissingEpisodesArray($rules, $formattor)
    {
        $missingEpisodesList = [];
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $missingEpisodesJSON = $this->sonarrApi->getWantedMissing($pageToSearch, 10, 'airDateUtc', 'desc');
            LogSonarr::debug('JSON FOR MISSINGS' . $missingEpisodesJSON);
            $missingEpisodes = $this->utils->verifyJson($missingEpisodesJSON);
            if ($missingEpisodes == NULL || empty($missingEpisodes['records'])) {
                LogSonarr::info("stop searching for missing episodes, no more episodes");
                $stopSearch = true;
            }
            foreach ($missingEpisodes['records'] as $serie) {
                $episodeTitle = $serie["series"]["title"];
                // Verify date rule
                $numberDaysToRetrieve = $rules["numberDays"];
                $anteriorDate = $this->utils->getAnteriorDateForNumberDay($numberDaysToRetrieve);
                LogSonarr::debug("anterior date timestamp is " . $anteriorDate);
                $airDateEpisode = strtotime($serie["airDateUtc"]);
                LogSonarr::debug("air date timestamp for " . $episodeTitle . " is " . $airDateEpisode);
                if ($stopSearch == false && $airDateEpisode > $anteriorDate) {
                    LogSonarr::debug("airDate for " . $episodeTitle . " is after the anterior date");
                    // We can add the episode
                    $seriesId = $serie["seriesId"];
                    $seasonNumber = $serie["seasonNumber"];
                    $episodeNumber = $serie["episodeNumber"];
                    $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber, $formattor);
                    $ddl_date_str = $serie["airDateUtc"];
                    $ddl_date_str = $this->utils->formatDate($ddl_date_str);
                    $images = $serie["series"]["images"];
                    $urlImage = "";
                    foreach ($images as $image) {
                        if ($image["coverType"] == "poster") {
                            $urlImage =  $image["url"];
                        }
                    }
                    //Save image
                    $this->saveImage($urlImage, $seriesId);
                    $episodeImage = array(
                        'title' => $episode,
                        'serie' => $episodeTitle,
                        'image' => $urlImage,
                        'seriesId' => $seriesId,
                        'date' => $ddl_date_str,
                    );
                    array_push($missingEpisodesList, $episodeImage);
                } else if ($stopSearch == false) {
                    LogSonarr::debug("airDate for " . $episodeTitle . " is before the anterior date, stop searching");
                    $stopSearch = true;
                }
            }
            $pageToSearch++;
        }
        $missingEpisodesList = $this->utils->applyMaxRulesToArray($missingEpisodesList, $rules);
        return $missingEpisodesList;
    }
    public function getDownladedEpisodesFormattedList($context, $rules, $separator, $formattor, $groupEpisode, $separatorEpisodes)
    {
        $ddlEpisodesList = $this->getDownloadedEpisodesArray($rules);
        $ddlEpisodesList = sonarrUtils::getEpisodesMoviesList($ddlEpisodesList, $groupEpisode, $formattor, $separatorEpisodes);
        // Save RAW
        $context->checkAndUpdateCmd('day_ddl_episodes_raw', json_encode($ddlEpisodesList));
        // Format list
        $listOnlyTitle = [];
        foreach ($ddlEpisodesList as $ddlObj) {
            array_push($listOnlyTitle, $ddlObj['title']);
        }
        $dowloadedEpisodesList =  implode($separator, $listOnlyTitle);
        if ($dowloadedEpisodesList == "") {
            LogSonarr::info('no downloaded episodes');
        }
        // Save format
        $context->checkAndUpdateCmd('day_ddl_episodes', $dowloadedEpisodesList);
    }

    public function notifyEpisode($caller, $last_refresh_date, $context, $formattor, $groupEpisode, $separatorEpisodes)
    {
        LogSonarr::info('date du dernier refresh : ' . $last_refresh_date);
        $last_refresh_date = strtotime($last_refresh_date);
        $list_episodesImgs = $this->getHistoryForDate($last_refresh_date);
        $list_episodesImgs = sonarrUtils::getEpisodesMoviesList($list_episodesImgs, $groupEpisode, $formattor, $separatorEpisodes);
        $this->utils->sendNotificationForTitleImgArray($caller, $list_episodesImgs, $context, $formattor);
    }

    public function getDownloadedEpisodesArray($rules)
    {
        $anteriorDate = $this->utils->getAnteriorDateForNumberDay($rules["numberDays"]);
        $ddlEpisodesList = $this->getHistoryForDate($anteriorDate);
        $ddlEpisodesList = $this->utils->applyMaxRulesToArray($ddlEpisodesList, $rules);
        return $ddlEpisodesList;
    }

    private function getHistoryForDate($last_refresh_date)
    {
        $episodeList = [];
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $historyJSON = $this->sonarrApi->getHistory($pageToSearch, 10, 'date', 'desc');
            LogSonarr::debug('JSON FOR HISTORY' . $historyJSON);
            $history = $this->utils->verifyJson($historyJSON);
            if ($history == NULL || empty($history['records'])) {
                LogSonarr::info("stop searching for new episode to notify empty history page");
                $stopSearch = true;
            }
            foreach ($history['records'] as $serie) {
                if ($stopSearch == false && $serie["eventType"] == "downloadFolderImported") {
                    $ddl_date_str = $serie["date"];
                    $ddl_date = strtotime($ddl_date_str);
                    if ($ddl_date > $last_refresh_date || $last_refresh_date == NULL) {
                        if ($last_refresh_date == NULL) {
                            LogSonarr::info('first run for notification');
                            $stopSearch = true;
                        }
                        $seriesId = $serie["seriesId"];
                        $episodeTitle = $serie["series"]["title"];
                        $seasonNumber = $serie["episode"]["seasonNumber"];
                        $episodeNumber = $serie["episode"]["episodeNumber"];
                        $quality = $serie["quality"]["quality"]["resolution"] . "p";
                        $images = $serie["series"]["images"];
                        $urlImage = "";
                        foreach ($images as $image) {
                            if ($image["coverType"] == "poster") {
                                $urlImage =  $image["url"];
                            }
                        }
                        //Save image
                        $this->saveImage($urlImage, $seriesId);
                        // We have to find specifics informations on the episode
                        $size = $this->retrieveSizeForEpisode($serie["episodeId"]);
                        //$missingEpisodeNumber = $this->retrieveNumberMissingEpForSerie($serie["seriesId"]);
                        $ddl_date_str = $this->utils->formatDate($ddl_date_str);
                        $episodeImage = array(
                            'serie' => $episodeTitle,
                            'season' => $seasonNumber,
                            'episode' => $episodeNumber,
                            'quality' => $quality,
                            'size' => $size,
                            'date' => $ddl_date_str,
                            'image' => $urlImage,
                            'seriesId' => $seriesId,
                        );
                        array_push($episodeList, $episodeImage);
                        LogSonarr::info("found new episode downladed :" . $serie["series"]["title"]);
                    } else {
                        LogSonarr::info("stop searching for new episode to notify");
                        $stopSearch = true;
                    }
                }
            }
            $pageToSearch++;
        }
        return $episodeList;
    }
    public function retrieveWidgetsDatas()
    {
    }
    private function retrieveSizeForEpisode($episodeId)
    {
        $informationsEpisode = $this->getInformationsEpisodes($episodeId);
        $sizeByte = $informationsEpisode["episodeFile"]["size"];
        return $this->utils->formatSize($sizeByte);
    }
    private function getInformationsEpisodes($episodeId)
    {
        $episodeInfoJSON = $this->sonarrApi->getEpisode($episodeId);
        LogSonarr::debug('JSON FOR SPECIFIC EPISODE' . $episodeInfoJSON);
        $episodeInfo = $this->utils->verifyJson($episodeInfoJSON);
        if ($episodeInfo == NULL) {
            return "";
        } else {
            return $episodeInfo;
        }
    }
    private function retrieveNumberMissingEpForSerie($seriesId)
    {
        $informationsSerie = $this->getInformationsSeries($seriesId);
        $numberEpisodeMissing = 0;
        foreach ($informationsSerie["seasons"] as $season) {
            if ($season["monitored"]) {
                $numberEpisodeMissing += $season["totalEpisodeCount"] - $season["episodeFileCount"];
            }
        }
        return $numberEpisodeMissing;
    }
    private function getInformationsSeries($seriesId)
    {
        $seriesInfoJSON = $this->sonarrApi->getSeries($seriesId);
        LogSonarr::debug('JSON FOR SPECIFIC SERIES' . $seriesInfoJSON);
        $seriesInfo = $this->utils->verifyJson($seriesInfoJSON);
        if ($seriesInfo == NULL) {
            return "";
        } else {
            return $seriesInfo;
        }
    }
    public function getMonitoredSeries($separator)
    {
        $listSeriesJSON = $this->sonarrApi->getSeries();
        LogSonarr::debug('JSON FOR SERIES ' . $listSeriesJSON);
        $listSeries = $this->utils->verifyJson($listSeriesJSON);
        if ($listSeries == NULL) {
            LogSonarr::info('There are nos series in your sonarr');
            return "";
        }
        $liste_series = "";
        foreach ($listSeries as $serie) {
            if ($serie["monitored"] == true) {
                $liste_series = $this->utils->formatList($liste_series, $serie["title"], $separator);
            }
        }
        return $liste_series;
    }

    public function getSystemStatus()
    {
        $statusJSON = $this->sonarrApi->getDiskspace();
        LogSonarr::info('JSON FOR DISKSPACE ' . $statusJSON);
    }

    private function saveImage($url, $imageName)
    {
        $img = '/var/www/html/plugins/sonarr/core/template/dashboard/imgs/sonarr_' . $imageName . '.jpg';
        file_put_contents($img, file_get_contents($url));
    }

    public function searchForSerie($context, $queryTerms)
    {
        LogSonarr::info('----------------------------------');
        LogSonarr::info('START SERIE SEARCH ' . $context->getName() . ' with terms: ' . $queryTerms);
        // Check needed cmd
        $searchResult = SonarrRadarrUtils::verifyCmd($context, 'search_result');
        $searchResultRaw = SonarrRadarrUtils::verifyCmd($context, 'search_result_raw');
        if ($searchResult == null || $searchResultRaw == null) {
            return;
        }
        // Retrieve JSON
        $listSeriesJSON = $this->sonarrApi->getSeriesLookup($queryTerms);
        LogSonarr::debug('JSON FOR SEARCH ' . $listSeriesJSON);
        // Save RAW
        $searchResultRaw->event($listSeriesJSON);
        $series = new SeriesSearch($listSeriesJSON);
        // Format list
        $separator = $context->getSeparator();
        $seriesStr = '';
        foreach ($series->series as $serie) {
            $str = $serie->title . ' ' . $serie->year;
            $seriesStr = $this->utils->formatList($seriesStr, $str, $separator);
        }
        if ($seriesStr == "") {
            LogSonarr::info('no search results');
        }
        $searchResult->event($seriesStr);
        LogSonarr::info('END SERIE SEARCH ' . $context->getName());
        LogSonarr::info('----------------------------------');
    }
}

class SeriesSearch
{
    public $series;

    function __construct($dataJSON)
    {
        $data = json_decode($dataJSON, true);
        if ($data != '' && $data != null) {
            $array_series = array();
            foreach ($data as $value) {
                $serie = new SerieSearch($value);
                array_push($array_series, $serie);
            }
            $this->series = $array_series;
        } else {
        }
    }
}
class SerieSearch
{
    public $title;
    public $seasonCount;
    public $year;
    public $tvdbId;

    function __construct($data)
    {
        if (isset($data['title']))
            $this->title = $data['title'];

        if (isset($data['seasonCount']))
            $this->seasonCount = $data['seasonCount'];

        if (isset($data['year']))
            $this->year = $data['year'];

        if (isset($data['title']))
            $this->tvdbId = $data['tvdbId'];
    }
}
