<?php

require_once __DIR__  . '/radarrApi.class.php';
require_once __DIR__  . '/sonarrUtils.class.php';

class radarrApiWrapper
{
    protected $radarrApi;
    protected $utils;

    public function __construct($url, $apiKey)
    {
        if ($url == NULL || $url == "") {
            log::add('sonarr', 'error', 'No URL given, this plugin needs the URL to your Radarr to work');
        }
        if ($apiKey == NULL || $apiKey == "") {
            log::add('sonarr', 'error', 'No API KEY given, this plugin needs the API KEY of your Radarr to work');
        }
        $this->radarrApi = new radarrApi($url, $apiKey);
        $this->utils = new sonarrUtils();
    }

    public function getFutureMoviesFormattedList($separator, $rules)
    {
        $futurMoviesListStr = '';
        $futurMoviesList = $this->getFutureMoviesArray($rules);
        log::add('sonarr', 'info', 'Number of futur movies' . count($futurMoviesList));
        foreach ($futurMoviesList as $futurMovie) {
            log::add('sonarr', 'info', $futurMovie["title"] . ' is missing');
            $futurMoviesListStr = $this->utils->formatList($futurMoviesListStr, $futurMovie["title"], $separator);
        }
        return $futurMoviesListStr;
    }
    public function getFutureMoviesArray($rules)
    {
        $liste_movie = [];
        $dayFutures = $rules["numberDays"];
        $currentDate = new DateTime();
        $futureDate = new DateTime();
        $futureDate->add(new DateInterval('P' . $dayFutures . 'D'));
        $currentDate = $currentDate->format('Y-m-d');
        $futureDate = $futureDate->format('Y-m-d');
        // Server call
        log::add('sonarr', 'debug', 'fetching futures movies between ' . $currentDate . ' and ' . $futureDate);
        $calendar = $this->radarrApi->getCalendar($currentDate, $futureDate);
        log::add('sonarr', 'debug', 'JSON FOR CALENDAR' . $calendar);
        // return error if needed
        $calendar = $this->utils->verifyJson($calendar);
        if ($calendar == NULL) {
            return "";
        }
        $calendar = $this->utils->applyMaxRulesToArray($calendar, $rules);
        //Analyze datas
        foreach ($calendar as $movie) {
            $movieToNotify = $movie["title"];
            $moviesId = $movie["id"];
            $ddl_date_str = $movie["inCinemas"];
            $ddl_date_str = $this->utils->formatDate($ddl_date_str);
            $downloaded = $movie["hasFile"];
            $size = $movie["movieFile"]["size"];
            if ($size != null && $size != 0) {
                $size = $this->utils->formatSize($size);
            } else {
                $size = "";
            }
            $quality = $movie["movieFile"]["quality"]["quality"]["resolution"];
            if ($quality != null && $quality != 0) {
                $quality = $quality . "p";
            } else {
                $quality = "";
            }
            $images = $movie["images"];
            $urlImage = "";
            foreach ($images as $image) {
                if ($image["coverType"] == "poster") {
                    $urlImage =  $image["url"];
                }
            }
            $this->saveImage($urlImage, $moviesId);
            $movieObj = array(
                'title' => $movieToNotify,
                'image' => $urlImage,
                'seriesId' => $moviesId,
                'date' => $ddl_date_str,
                'downloaded' => $downloaded,
                'size' => $size,
                'quality' => $quality,
            );
            array_push($liste_movie, $movieObj);
        }
        return $liste_movie;
    }
    public function getMissingMoviesFormattedList($separator)
    {
        $missingMoviesListStr = "";
        $missingMoviesList = $this->getMissingMoviesArray(null);
        foreach ($missingMoviesList as $missingMovie) {
            $missingMoviesListStr = $this->utils->formatList($missingMoviesListStr, $missingMovie["title"], $separator);
        }
        return $missingMoviesListStr;
    }

    public function getMissingMoviesArray($rules)
    {
        $liste_movie = [];
        $moviesJSON = $this->radarrApi->getMovies();
        log::add('sonarr', 'debug', 'JSON FOR MOVIES' . $moviesJSON);
        // return error if needed
        $movies = $this->utils->verifyJson($moviesJSON);
        if ($movies == NULL) {
            return "";
        }
        // Analyze datas
        $missingMoviesList = [];
        foreach ($movies as $movie) {
            if ($movie["status"] == "released" && $movie["hasFile"] == false) {
                //Episode is missing
                array_push($missingMoviesList, $movie);
            }
        }
        if (empty($missingMoviesList)) {
            return "";
        }
        // Now that we have find all the missing movies, we have to sort them
        function compare_movies($a, $b)
        {
            return strtotime($b["inCinemas"]) - strtotime($a["inCinemas"]);
        }
        usort($missingMoviesList, "compare_movies");
        if ($rules != null) {
            $missingMoviesList = $this->utils->applyMaxRulesToArray($missingMoviesList, $rules);
        }
        foreach ($missingMoviesList as $movie) {
            $movieToNotify = $movie["title"];
            $moviesId = $movie["id"];
            $ddl_date_str = $movie["inCinemas"];
            $ddl_date_str = $this->utils->formatDate($ddl_date_str);

            $size = $movie["sizeOnDisk"];
            if ($size != null && $size != 0) {
                $size = $this->utils->formatSize($size);
            } else {
                $size = "";
            }
            $quality = $movie["movieFile"]["quality"]["quality"]["resolution"];
            if ($quality != null && $quality != 0) {
                $quality = $quality . "p";
            } else {
                $quality = "";
            }
            $images = $movie["images"];
            $urlImage = "";
            foreach ($images as $image) {
                if ($image["coverType"] == "poster") {
                    $urlImage =  $image["remoteUrl"];
                }
            }
            $this->saveImage($urlImage, $moviesId);
            $movieObj = array(
                'title' => $movieToNotify,
                'image' => $urlImage,
                'seriesId' => $moviesId,
                'date' => $ddl_date_str,
                'size' => $size,
                'quality' => $quality,
            );
            array_push($liste_movie, $movieObj);
        }
        return $liste_movie;
    }

    public function getDownladedMoviesFormattedList($rules, $separator)
    {
        $ddlMoviesList = $this->getDownloadedMoviesArray($rules);
        $ddlMoviesList = $this->utils->getEpisodesMoviesList($ddlMoviesList);
        return implode($separator, $ddlMoviesList);
    }

    public function getDownloadedMoviesArray($rules)
    {
        $anteriorDate = $this->utils->getAnteriorDateForNumberDay($rules["numberDays"]);
        $ddlMoviesList = $this->getHistoryForDate($anteriorDate);
        $ddlMoviesList = $this->utils->applyMaxRulesToArray($ddlMoviesList, $rules);
        return $ddlMoviesList;
    }

    public function notifyMovie($caller, $last_refresh_date, $context)
    {
        log::add('sonarr', 'info', 'date last refresh : ' . $last_refresh_date);
        $last_refresh_date = strtotime($last_refresh_date);
        $list_moviesImgs = $this->getHistoryForDate($last_refresh_date);
        $this->utils->sendNotificationForTitleImgArray($caller, $list_moviesImgs, $context);
    }

    private function getHistoryForDate($last_refresh_date)
    {
        $liste_movies = [];
        $stopSearch = false;
        $pageToSearch = 1;
        while ($stopSearch == false) {
            $historyJSON = $this->radarrApi->getHistory($pageToSearch, 10, 'date', 'desc');
            log::add('sonarr', 'debug', 'JSON FOR HISTORY' . $historyJSON);
            $history = $this->utils->verifyJson($historyJSON);
            if ($history == NULL || empty($history['records'])) {
                log::add('sonarr', 'info', "stop searching for movies");
                $stopSearch = true;
            }
            foreach ($history['records'] as $movie) {
                if ($stopSearch == false && $movie["eventType"] == "downloadFolderImported") {
                    $ddl_date_str = $movie["date"];
                    $ddl_date = strtotime($ddl_date_str);
                    if ($ddl_date > $last_refresh_date || $last_refresh_date == NULL) {
                        if ($last_refresh_date == NULL) {
                            log::add('sonarr', 'info', 'first run for notification');
                            $stopSearch = true;
                        }
                        $movieToNotify = $movie["movie"]["title"];
                        $moviesId = $movie["movie"]["id"];
                        $ddl_date_str = $movie["inCinemas"];
                        $ddl_date_str = $this->utils->formatDate($ddl_date_str);

                        $size = $movie["movie"]["sizeOnDisk"];
                        if ($size != null && $size != 0) {
                            $size = $this->utils->formatSize($size);
                        } else {
                            $size = "";
                        }
                        $quality = $movie["quality"]["quality"]["resolution"];
                        if ($quality != null && $quality != 0) {
                            $quality = $quality . "p";
                        } else {
                            $quality = "";
                        }
                        $images = $movie["movie"]["images"];
                        $urlImage = "";
                        foreach ($images as $image) {
                            if ($image["coverType"] == "poster") {
                                $urlImage =  $image["url"];
                            }
                        }
                        $this->saveImage($urlImage, $moviesId);
                        $movieObj = array(
                            'title' => $movieToNotify,
                            'image' => $urlImage,
                            'seriesId' => $moviesId,
                            'date' => $ddl_date_str,
                            'size' => $size,
                            'quality' => $quality,
                        );
                        array_push($liste_movies, $movieObj);
                        log::add('sonarr', 'info', "found new film downladed :" . $movieToNotify);
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
    private function saveImage($url, $imageName)
    {
        $img = '/var/www/html/plugins/sonarr/core/template/dashboard/imgs/radarr_' . $imageName . '.jpg';
        file_put_contents($img, file_get_contents($url));
    }
}
