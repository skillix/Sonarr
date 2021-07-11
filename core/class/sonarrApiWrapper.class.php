<?php

require_once __DIR__  . '/sonarrApi.class.php';
require_once __DIR__  . '/sonarrUtils.class.php';

class sonarrApiWrapper {
    protected $sonarrApi;
    protected $utils;

    public function __construct($url, $apiKey) {
        if ($url == NULL || $url == "") {
            log::add('sonarr', 'error', 'No URL given, this plugin needs the URL to your Sonarr to work');
        }
        if ($apiKey == NULL || $apiKey == "") {
            log::add('sonarr', 'error', 'No API KEY given, this plugin needs the API KEY of your Sonarr to work');
        }
        $this->sonarrApi = new sonarrApi($url, $apiKey);
        $this->utils = new sonarrUtils();
    }

    public function getFutureEpisodesFormattedList($separator, $rules, $formattor) {
        $futurEpisodesListStr = '';
        $futurEpisodesList = $this->getFutureEpisodesArray($rules, $formattor);
        foreach($futurEpisodesList as $futurEpisode) {
            $futurEpisodesListStr = $this->utils->formatList($futurEpisodesListStr, $futurEpisode["title"], $separator);
        }
    }
    public function getFutureEpisodesArray($rules, $formattor) {
        $liste_episode = [];
        $dayFutures = $rules["numberDays"];
        $currentDate = new DateTime();
        $futureDate = new DateTime();
        $futureDate->add(new DateInterval('P'.$dayFutures.'D'));
        $currentDate = $currentDate->format('Y-m-d');
        $futureDate = $futureDate->format('Y-m-d');
        // Server call
        log::add('sonarr', 'debug', 'fetching futures episodes between '.$currentDate.' and '.$futureDate);
        $calendar = $this->sonarrApi->getCalendar($currentDate, $futureDate);
        log::add('sonarr', 'debug', 'JSON FOR CALENDAR'.$calendar);
        // return error if needed
        $calendar = $this->utils->verifyJson($calendar);
        if ($calendar == NULL) {
           return "";
        }
        $calendar = $this->utils->applyMaxRulesToArray($calendar, $rules);
        // Analyze datas
        foreach($calendar as $serie) {
            $episodeTitle = $serie["series"]["title"];
            $seasonNumber = $serie["seasonNumber"];
            $episodeNumber = $serie["episodeNumber"];
            $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber, $formattor);
            $seriesId = $serie["seriesId"];
            $ddl_date_str = $serie["airDateUtc"];
            $ddl_date_str = $this->utils->formatDate($ddl_date_str);
            $images = $serie["series"]["images"];
            $urlImage = "";
            foreach($images as $image) {
                if ($image["coverType"] == "poster") {
                    $urlImage =  $image["url"];
                }
            }
            //Save image
            $this->utils->saveImage($urlImage, $seriesId);
            $downloaded = $serie["hasFile"];
            $size = $this->utils->formatSize($serie["episodeFile"]["size"]);
            $quality = $serie["episodeFile"]["quality"]["quality"]["resolution"]."p";
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
    public function getMissingEpisodesFormattedList($rules, $separator, $formattor) {
        $missingEpisodesListStr = "";
        $missingEpisodesList = $this->getMissingEpisodesArray($rules, $formattor);
        foreach($missingEpisodesList as $missingEpisode) {
            $missingEpisodesListStr = $this->utils->formatList($missingEpisodesListStr, $missingEpisode["title"], $separator);
        }
        return $missingEpisodesListStr;
    }
    public function getMissingEpisodesArray($rules, $formattor) {
        $missingEpisodesList = [];
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $missingEpisodesJSON = $this->sonarrApi->getWantedMissing($pageToSearch, 10, 'airDateUtc', 'desc');
            log::add('sonarr', 'debug', 'JSON FOR MISSINGS'.$missingEpisodesJSON);
            $missingEpisodes = $this->utils->verifyJson($missingEpisodesJSON);
            if ($missingEpisodes == NULL || empty($missingEpisodes['records'])) { 
                log::add('sonarr', 'info', "stop searching for missing episodes, no more episodes");
                $stopSearch = true;
            }
            foreach($missingEpisodes['records'] as $serie) {
                $episodeTitle = $serie["series"]["title"];
                // Verify date rule
                $numberDaysToRetrieve = $rules["numberDays"];
                $anteriorDate = $this->utils->getAnteriorDateForNumberDay($numberDaysToRetrieve);
                log::add('sonarr', 'debug', "anterior date timestamp is ".$anteriorDate);
                $airDateEpisode = strtotime($serie["airDateUtc"]);
                log::add('sonarr', 'debug', "air date timestamp for ".$episodeTitle." is ".$airDateEpisode);
                if ($stopSearch == false && $airDateEpisode > $anteriorDate) {
                    log::add('sonarr', 'debug', "airDate for ".$episodeTitle." is after the anterior date");
                    // We can add the episode
                    $seriesId = $serie["seriesId"];
                    $seasonNumber = $serie["seasonNumber"];
                    $episodeNumber = $serie["episodeNumber"];
                    $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber, $formattor);
                    $ddl_date_str = $serie["airDateUtc"];
                    $ddl_date_str = $this->utils->formatDate($ddl_date_str);
                    $images = $serie["series"]["images"];
                    $urlImage = "";
                    foreach($images as $image) {
                        if ($image["coverType"] == "poster") {
                            $urlImage =  $image["url"];
                        }
                    }
                    //Save image
                    $this->utils->saveImage($urlImage, $seriesId);
                    $episodeImage = array(
                        'title' => $episode,
                        'serie' => $episodeTitle,
                        'image' => $urlImage,
                        'seriesId' => $seriesId,
                        'date' => $ddl_date_str,
                    );
                    array_push($missingEpisodesList, $episodeImage);
                } else if ($stopSearch == false) {
                    log::add('sonarr', 'debug', "airDate for ".$episodeTitle." is before the anterior date, stop searching");
                    $stopSearch = true;
                }
            }
            $pageToSearch++;
        }
        $missingEpisodesList = $this->utils->applyMaxRulesToArray($missingEpisodesList, $rules);
        return $missingEpisodesList;
    }
    public function getDownladedEpisodesFormattedList($rules, $separator, $formattor) {
        $ddlEpisodesList = $this->getDownloadedEpisodesArray($rules, $formattor);
        $ddlEpisodesList = $this->utils->getEpisodesMoviesList($ddlEpisodesList);
        return implode($separator, $ddlEpisodesList);
    }

    public function notifyEpisode($caller, $last_refresh_date, $context, $formattor) {
        log::add('sonarr', 'info', 'date du dernier refresh : '.$last_refresh_date);
        $last_refresh_date = strtotime($last_refresh_date);
        $list_episodesImgs = $this->getHistoryForDate($last_refresh_date, $formattor);
        $this->utils->sendNotificationForTitleImgArray($caller, $list_episodesImgs, $context);
    }

    public function getDownloadedEpisodesArray($rules, $formattor) {
        $anteriorDate = $this->utils->getAnteriorDateForNumberDay($rules["numberDays"]);
        $ddlEpisodesList = $this->getHistoryForDate($anteriorDate, $formattor);
        $ddlEpisodesList = $this->utils->applyMaxRulesToArray($ddlEpisodesList, $rules);
        return $ddlEpisodesList;
    }

    private function getHistoryForDate($last_refresh_date, $formattor) {
        $episodeList = [];
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $historyJSON = $this->sonarrApi->getHistory($pageToSearch, 10, 'date', 'desc');
            log::add('sonarr', 'debug', 'JSON FOR HISTORY'.$historyJSON);
            $history = $this->utils->verifyJson($historyJSON);
            if ($history == NULL || empty($history['records'])) { 
                log::add('sonarr', 'info', "stop searching for new episode to notify empty history page");
                $stopSearch = true;
            }
            foreach($history['records'] as $serie) {
                if ($stopSearch == false && $serie["eventType"] == "downloadFolderImported") {
                    $ddl_date_str = $serie["date"];
                    $ddl_date = strtotime($ddl_date_str);
                    if ($ddl_date > $last_refresh_date || $last_refresh_date == NULL) {
                        if ($last_refresh_date == NULL) {
                            log::add('sonarr', 'info', 'first run for notification');
                            $stopSearch = true;
                        }
                        $seriesId = $serie["seriesId"];
                        $episodeTitle = $serie["series"]["title"];
                        $seasonNumber = $serie["episode"]["seasonNumber"];
                        $episodeNumber = $serie["episode"]["episodeNumber"];
                        $quality = $serie["quality"]["quality"]["resolution"]."p";
                        $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber, $formattor);
                        $images = $serie["series"]["images"];
                        $urlImage = "";
                        foreach($images as $image) {
                            if ($image["coverType"] == "poster") {
                                $urlImage =  $image["url"];
                            }
                        }
                        //Save image
                        $this->utils->saveImage($urlImage, $seriesId);
                        // We have to find specifics informations on the episode
                        $size = $this->retrieveSizeForEpisode($serie["episodeId"]);
                        //$missingEpisodeNumber = $this->retrieveNumberMissingEpForSerie($serie["seriesId"]);
                        $ddl_date_str = $this->utils->formatDate($ddl_date_str);
                        $episodeImage = array(
                            'title' => $episode,
                            'quality' => $quality,
                            'size' => $size,
                            //'missingEpNumber' => $missingEpisodeNumber,
                            'date' => $ddl_date_str,
                            'serie' => $episodeTitle,
                            'image' => $urlImage,
                            'seriesId' => $seriesId,
                        );
                        array_push($episodeList, $episodeImage);
                        log::add('sonarr', 'info', "found new episode downladed :".$serie["series"]["title"]);
                    } else {
                        log::add('sonarr', 'info', "stop searching for new episode to notify");
                        $stopSearch = true;
                    }
                }
            }
            $pageToSearch++;
        }
        return $episodeList;
    }
    private function retrieveSizeForEpisode($episodeId) {
        $informationsEpisode = $this->getInformationsEpisodes($episodeId);
        $sizeByte = $informationsEpisode["episodeFile"]["size"];
        return $this->utils->formatSize($sizeByte);
    }
    private function getInformationsEpisodes($episodeId) {
        $episodeInfoJSON = $this->sonarrApi->getEpisode($episodeId);
        log::add('sonarr', 'debug', 'JSON FOR SPECIFIC EPISODE'.$episodeInfoJSON);
        $episodeInfo = $this->utils->verifyJson($episodeInfoJSON);
        if ($episodeInfo == NULL) {
           return "";
        } else {
            return $episodeInfo;
        }
    }
    private function retrieveNumberMissingEpForSerie($seriesId) {
        $informationsSerie = $this->getInformationsSeries($seriesId);
        $numberEpisodeMissing = 0;
        foreach($informationsSerie["seasons"] as $season) {
            if ($season["monitored"]) {
                $numberEpisodeMissing += $season["totalEpisodeCount"] - $season["episodeFileCount"];
            }
        }
        return $numberEpisodeMissing;
    }
    private function getInformationsSeries($seriesId) {
        $seriesInfoJSON = $this->sonarrApi->getSeries($seriesId);
        log::add('sonarr', 'debug', 'JSON FOR SPECIFIC SERIES'.$seriesInfoJSON);
        $seriesInfo = $this->utils->verifyJson($seriesInfoJSON);
        if ($seriesInfo == NULL) {
           return "";
        } else {
            return $seriesInfo;
        }
    }
    public function getMonitoredSeries($separator) {
        $listSeriesJSON = $this->sonarrApi->getSeries();
        log::add('sonarr', 'debug', 'JSON FOR SERIES '.$listSeriesJSON);
        $listSeries = $this->utils->verifyJson($listSeriesJSON);
        if ($listSeries == NULL) {
            log::add('sonarr', 'info', 'There are nos series in your sonarr');
            return "";
        }
        $liste_series = "";
        foreach($listSeries as $serie) {
            if ($serie["monitored"] == true) {
                $liste_series = $this->utils->formatList($liste_series, $serie["title"], $separator);
            }
        }
        return $liste_series;
    }

    public function getSystemStatus() {
        $statusJSON = $this->sonarrApi->getDiskspace();
        log::add('sonarr', 'info', 'JSON FOR DISKSPACE '.$statusJSON);
    }
}