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

    public function getFutureEpisodes($separator, $dayFutures) {
        $liste_episode = "";
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
        // Analyze datas
        foreach($calendar as $serie) {
           $episodeTitle = $serie["series"]["title"];
           $seasonNumber = $serie["seasonNumber"];
           $episodeNumber = $serie["episodeNumber"];
           $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber);
           $liste_episode = $this->utils->formatList($liste_episode, $episode, $separator);
        }
        return $liste_episode;
    }
    public function getMissingEpisodes($numberToFetch, $separator) {
        $liste_episode = "";
        $missingEpisodesJSON = $this->sonarrApi->getWantedMissing(1, $numberToFetch, 'airDateUtc', 'desc');
        $missingEpisodes = $this->utils->verifyJson($missingEpisodesJSON);
        if ($missingEpisodes == NULL) {
           return "";
        }
        foreach($missingEpisodes['records'] as $serie) {
           $episodeTitle = $serie["series"]["title"];
           $seasonNumber = $serie["seasonNumber"];
           $episodeNumber = $serie["episodeNumber"];
           $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber);
           $liste_episode = $this->utils->formatList($liste_episode, $episode, $separator);
        }
        return $liste_episode;
    }
    public function getDownladedEpisodes($numberToFetch, $separator) {
        $list_episodesImgs = $this->getHistory($numberToFetch);
        $list_episodes = $this->utils->getEpisodesList($list_episodesImgs);
        return implode($separator, $list_episodes);
    }

    public function notifyEpisode($last_refresh_date, $context) {
        log::add('sonarr', 'info', 'date du dernier refresh : '.$last_refresh_date);
        $list_episodesImgs = $this->getHistoryForDate($last_refresh_date);
        $this->utils->sendNotificationForTitleImgArray($list_episodesImgs, $context);
    }
    private function getHistoryForDate($last_refresh_date_str) {
        $liste_episode = [];
        $last_refresh_date = strtotime($last_refresh_date_str);
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $historyJSON = $this->sonarrApi->getHistory($pageToSearch, 10, 'date', 'desc');
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
                        $episodeTitle = $serie["series"]["title"];
                        $seasonNumber = $serie["episode"]["seasonNumber"];
                        $episodeNumber = $serie["episode"]["episodeNumber"];
                        $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber);
                        $images = $serie["series"]["images"];
                        $urlImage = "";
                        foreach($images as $image) {
                            if ($image["coverType"] == "poster") {
                                $urlImage =  $image["url"];
                            }
                        }
                        $episodeImage = array(
                            'title' => $episode,
                            'image' => $urlImage,
                        );
                        array_push($liste_episode, $episodeImage);
                        log::add('sonarr', 'info', "found new episode downladed :".$serie["series"]["title"]);
                    } else {
                        log::add('sonarr', 'info', "stop searching for new episode to notify");
                        $stopSearch = true;
                    }
                }
            }
            $pageToSearch++;
        }
        return $liste_episode;
    }
    private function getHistory($numberToFetch) {
        $numberMax = $numberToFetch * 4;
        $liste_episode = [];
        $historyJSON = $this->sonarrApi->getHistory(1, $numberMax, 'date', 'desc');
        $history = $this->utils->verifyJson($historyJSON);
        if ($history == NULL) {
           return [];
        }
        foreach($history['records'] as $serie) {
            if (count($liste_episode) < $numberToFetch && strcmp($serie["eventType"] , "downloadFolderImported") == 0) {
                $episodeTitle = $serie["series"]["title"];
                $seasonNumber = $serie["episode"]["seasonNumber"];
                $episodeNumber = $serie["episode"]["episodeNumber"];
                $episode = $this->utils->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber);
                $images = $serie["series"]["images"];
                $urlImage = "";
                foreach($images as $image) {
                    if ($image["coverType"] == "poster") {
                        $urlImage =  $image["url"];
                    }
                }
                $episodeImage = array(
                    'episode' => $episode,
                    'image' => $urlImage,
                );
                array_push($liste_episode, $episodeImage);
            }
        }
        return $liste_episode;
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
}