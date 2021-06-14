<?php

require_once __DIR__  . '/radarrApi.class.php';
require_once __DIR__  . '/sonarrUtils.class.php';

class radarrApiWrapper {
    protected $radarrApi;
    protected $utils;

    public function __construct($url, $apiKey) {
        if ($url == NULL || $url == "") {
            log::add('sonarr', 'error', 'No URL given, this plugin needs the URL to your Radarr to work');
        }
        if ($apiKey == NULL || $apiKey == "") {
            log::add('sonarr', 'error', 'No API KEY given, this plugin needs the API KEY of your Radarr to work');
        }
        $this->radarrApi = new radarrApi($url, $apiKey);
        $this->utils = new sonarrUtils();
    }

    public function getFutureMovies($separator, $dayFutures) {
        $liste_movies = "";
        $currentDate = new DateTime();
        $futureDate = new DateTime();
        $futureDate->add(new DateInterval('P'.$dayFutures.'D'));
        $currentDate = $currentDate->format('Y-m-d');
        $futureDate = $futureDate->format('Y-m-d');
        // Server call
        log::add('sonarr', 'debug', 'fetching futures movies between '.$currentDate.' and '.$futureDate);
        $calendar = $this->radarrApi->getCalendar($currentDate, $futureDate);
        log::add('sonarr', 'debug', 'JSON FOR CALENDAR'.$calendar);
        // return error if needed
        $calendar = $this->utils->verifyJson($calendar);
        if ($calendar == NULL) {
           return "";
        }
        // Analyze datas
        foreach($calendar as $movie) {
           $movieTitle = $movie["title"];
           $liste_movies = $this->utils->formatList($liste_movies, $movieTitle, $separator);
        }
        return $liste_movies;
    }
    public function getMissingMovies($separator) {
        $liste_movies = "";
        $moviesJSON = $this->radarrApi->getMovies();
        log::add('sonarr', 'debug', 'JSON FOR MOVIES'.$moviesJSON);
        // return error if needed
        $movies = $this->utils->verifyJson($moviesJSON);
        if ($movies == NULL) {
           return "";
        }
        // Analyze datas
        $missingMoviesList = [];
        foreach($movies as $movie) {
            if ($movie["status"] == "released" && $movie["hasFile"] == false) {
                //Episode is missing
                array_push($missingMoviesList, $movie);
            }
        }
        if (empty($missingMoviesList)) {
            return "";
        }
        // Now that we have find all the missing movies, we have to sort them
        function compare_movies($a, $b) {
            return strtotime($b["inCinemas"]) - strtotime($a["inCinemas"]);
        }
        usort($missingMoviesList, "compare_movies");
        foreach($missingMoviesList as $movie) {
            $movieTitle = $movie["title"];
            $liste_movies = $this->utils->formatList($liste_movies, $movieTitle, $separator);
        }
        return $liste_movies;
    }

    public function getDownladedMovies($number, $separator) {
        $ddlMoviesList = [];
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $historyJSON = $this->radarrApi->getHistory($pageToSearch, $number, 'date', 'desc');
            log::add('sonarr', 'debug', 'JSON FOR HISTORY'.$historyJSON);
            $history = $this->utils->verifyJson($historyJSON);
            if ($history == NULL || empty($history['records'])) { 
                log::add('sonarr', 'info', "stop searching for movies");
                $stopSearch = true;
            }
            foreach($history['records'] as $movie) {
                if ($stopSearch == false && $movie["eventType"] == "downloadFolderImported") {
                    array_push($ddlMoviesList, $movie);
                    if (count($ddlMoviesList) == $number) {
                        $stopSearch = true;
                    }
                }
            }
            $pageToSearch++;
        }
        $liste_movies = "";
        foreach($ddlMoviesList as $movie) {
            $movieTitle = $movie["movie"]["title"];
            $liste_movies = $this->utils->formatList($liste_movies, $movieTitle, $separator);
        }
        return $liste_movies;
    }

    public function notifyMovie($last_refresh_date, $context) {
        log::add('sonarr', 'info', 'date last refresh : '.$last_refresh_date);
        $list_moviesImgs = $this->getHistoryForDate($last_refresh_date);
        $this->utils->sendNotificationForTitleImgArray($list_moviesImgs, $context);
    }
    
    private function getHistoryForDate($last_refresh_date_str) {
        $liste_movies = [];
        $last_refresh_date = strtotime($last_refresh_date_str);
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $historyJSON = $this->radarrApi->getHistory($pageToSearch, 10, 'date', 'desc');
            log::add('sonarr', 'debug', 'JSON FOR HISTORY'.$historyJSON);
            $history = $this->utils->verifyJson($historyJSON);
            if ($history == NULL || empty($history['records'])) { 
                log::add('sonarr', 'info', "stop searching for movies");
                $stopSearch = true;
            }
            foreach($history['records'] as $movie) {
                if ($stopSearch == false && $movie["eventType"] == "downloadFolderImported") {
                    $ddl_date_str = $movie["date"];
                    $ddl_date = strtotime($ddl_date_str);
                    if ($ddl_date > $last_refresh_date || $last_refresh_date == NULL) {
                        if ($last_refresh_date == NULL) {
                            log::add('sonarr', 'info', 'first run for notification');
                            $stopSearch = true;
                        }
                        $movieToNotify = $movie["movie"]["title"];
                        $images = $movie["movie"]["images"];
                        $urlImage = "";
                        foreach($images as $image) {
                            if ($image["coverType"] == "poster") {
                                $urlImage =  $image["url"];
                            }
                        }
                        $movieImage = array(
                            'title' => $movieToNotify,
                            'image' => $urlImage,
                        );
                        array_push($liste_movies, $movieImage);
                        log::add('sonarr', 'info', "found new film downladed :".$movieToNotify);
                    } else {
                        log::add('sonarr', 'info', "stop searching for new movies to notify");
                        $stopSearch = true;
                    }
                }
            }
            $pageToSearch++;
        }
        return $liste_movies;
    }
}