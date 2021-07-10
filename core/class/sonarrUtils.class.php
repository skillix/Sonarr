<?php
class sonarrUtils {

    public function getConfigurationFor($context, $numberDaysConfig, $numberMaxConfig) {
        $numberDays = $context->getConfiguration($numberDaysConfig);
        if ($numberDays == NULL || !is_numeric($numberDays)) {
            $numberDays = 1;
        }
        log::add('sonarr', 'info', 'Configuration for '.$numberDaysConfig.' is set to '.$numberDays);
        $numberMax = $context->getConfiguration($numberMaxConfig);
        if ($numberMax == NULL && !is_numeric($numberMax)) {
            $numberMax = NULL;
            log::add('sonarr', 'info', 'Configuration for '.$numberMaxConfig.' not set, will use only day rule');
        } else {
            log::add('sonarr', 'info', 'Configuration for '.$numberMaxConfig.' is set to '.$numberMax);
        }
        $rules = array(
            'numberDays' => $numberDays,
            'numberMax' => $numberMax,
        );
        return $rules;
    }
    public function saveImage($url, $imageName) {
        $img = '/var/www/html/plugins/sonarr/core/template/dashboard/imgs/sonarr_'.$imageName.'.jpg'; 
        file_put_contents($img, file_get_contents($url));
    }
    public function applyMaxRulesToArray($arrayToFormat, $rules) {
        $numberMax = $rules["numberMax"];
        return $this->applyMaxToArray($arrayToFormat, $numberMax);
    }
    public function applyMaxToArray($arrayToFormat, $numberMax) {
        log::add('sonarr', 'debug', 'will return the '.$numberMax.' elements of the array');
        if ($numberMax != NULL && $numberMax < count($arrayToFormat)) {
            log::add('sonarr', 'debug', 'Need to reformat the array with max number');
            $formattedArray = [];
            for ($i = 0; $i < $numberMax; $i++) {
                array_push($formattedArray, $arrayToFormat[$i]);
            }
            return $formattedArray;        
        } else {
            log::add('sonarr', 'debug', 'Number max to return is superior to the size of the array. Return the whole array');
            return $arrayToFormat;
        }
    }
    public function verifyJson($jsonToVerify) {
        $decodeJson = json_decode($jsonToVerify, true);
        if ($decodeJson['error'] != NULL) {
           $msg = $decodeJson['error']['msg'];
           log::add('sonarr', 'warning', 'There was an issue with the connection to Sonarr / Radarr :'.$msg);
           return NULL;
        }
        return $decodeJson;
    }
    public function getAnteriorDateForNumberDay($numberDays) {
        $anteriorDate = new DateTime();
        $anteriorDate->sub(new DateInterval('P'.$numberDays.'D'));
        $anteriorDate = $anteriorDate->getTimestamp();
        return $anteriorDate;
    }
    public function formatEpisode($episodeTitle, $seasonNumber, $episodeNumber, $formattor) { 
        $utf8 = "UTF-8";
        $formatorSeason = "%s";
        $formatorEpisode = "%e";
        $posSeason = mb_strpos($formattor, $formatorSeason, 0, $utf8);
        $posEpisode = mb_strpos($formattor, $formatorEpisode, 0, $utf8);
        log::add('sonarr', 'debug', 'selected formattor '.$formattor);
        if ($posSeason !== false && $posEpisode !== false) {
            log::add('sonarr', 'debug', 'found %s and %e in formattor');
            // We have season and episode formattor
            if ($posSeason < $posEpisode) {
                log::add('sonarr', 'debug', '%s is before %e');
                // Season is before episode
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorSeason, 0, $utf8), $utf8);
                log::add('sonarr', 'debug', 'string for season formattor: '.$seasonStr);
                $length = mb_strlen($seasonStr, $utf8) + mb_strlen($formatorSeason, $utf8);
                log::add('sonarr', 'debug', 'length of first part with formattor: '.$length);
                $numberToTakeForEpisode = mb_strpos($formattor, $formatorEpisode, 0, $utf8) - $length;
                log::add('sonarr', 'debug', 'number to take for second part: '.$numberToTakeForEpisode);
                $episodeStr = mb_substr($formattor, $length, $numberToTakeForEpisode, $utf8);
                log::add('sonarr', 'debug', 'string for episode formattor: '.$episodeStr);
                return $episodeTitle." ".$seasonStr.$seasonNumber.$episodeStr.$episodeNumber;
            } else {
                log::add('sonarr', 'debug', '%s is after %e');
                // Episode is before season
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorEpisode, 0, $utf8), $utf8);
                log::add('sonarr', 'debug', 'string for episode formattor: '.$episodeStr);
                $length = mb_strlen($episodeStr, $utf8) + mb_strlen($formatorEpisode, $utf8);
                log::add('sonarr', 'debug', 'length of first part with formattor: '.$length);
                $numberToTakeForSeason = mb_strpos($formattor, $formatorSeason, 0, $utf8) - $length;
                log::add('sonarr', 'debug', 'number to take for second part: '.$numberToTakeForSeason);
                $seasonStr = mb_substr($formattor, $length, $numberToTakeForSeason, $utf8);
                log::add('sonarr', 'debug', 'string for season formattor: '.$seasonStr);
                return $episodeTitle." ".$episodeStr.$episodeNumber.$seasonStr.$seasonNumber;
            }
        } else {
            if ($posSeason !== false) {
                log::add('sonarr', 'debug', 'only %s is present in: '.$posSeason);
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorSeason, 0, $utf8), $utf8);
                return $episodeTitle." ".$seasonStr.$seasonNumber;
            } else if ($posEpisode !== false) {
                log::add('sonarr', 'debug', 'only %e is present in: '.$posEpisode);
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorEpisode, 0, $utf8), $utf8);
                return $episodeTitle." ".$episodeStr.$episodeNumber;
            } else {
                log::add('sonarr', 'debug', 'no formattor');
                return $episodeTitle." "."S".$seasonNumber."E".$episodeNumber;
            }
        }
    }

    public function getEpisodesMoviesList($listObject) {
        $listFormattedObject = [];
        foreach($listObject as $object) {
            array_push($listFormattedObject, $object["title"]);
        }
        return $listFormattedObject;
    }

    public function formatList($list, $episode, $separator) {
        if ($list == "") {
           $list = $episode;
        } else {
           $list = $list.$separator.$episode;
        }
        return $list;
    }
    public function sendNotificationForTitleImgArray($caller, $titleImgArray, $context) {
        // Reverse Array to be asc
        $titleImgArray = array_reverse($titleImgArray);
        log::add('sonarr', 'info', "will send notification for ".count($titleImgArray)." movies/series");
        foreach($titleImgArray as $titleImg) {
            $formattedTitle = $this->formatTitleImg($titleImg);
            log::add('sonarr', 'info', "send notification for ".$formattedTitle);
            $context->getCmd(null, 'notification')->event($formattedTitle);
            $context->getCmd(null, 'last_episode')->event($titleImg["title"]);
            $notificationHTML = $this->formatHTMLNotification($caller, $titleImg["title"], $titleImg["quality"], $titleImg["size"], $titleImg["date"], $titleImg["serie"], $titleImg["missingEpNumber"], $titleImg["image"]);
            $context->getCmd(null, 'notificationHTML')->event($notificationHTML);
            sleep(1);
        }
    }
    public function formatDate($ddlObjDate) {
        $ddlObjDateFmatted = strtotime($ddlObjDate);
        $date = new DateTime();
        $date->setTimestamp($ddlObjDateFmatted);
        $ddlObjDateFmatted = $date->format('d/m/Y H:i:s');
        return $ddlObjDateFmatted;
    }
    private function formatHTMLNotification($caller, $ddlObjName, $ddlObjQuality, $ddlObjSize, $ddlObjDate, $ddlSerieName, $ddlObjMissingNumber, $ddlObjPoster) {
        if ($caller == 'sonarr') {
            $application = "Sonarr";
            $type = "nouvel épisode";
        } else if ($caller == 'radarr') {
            $application = "Radarr";
            $type = "nouveau film";
        }
        $HTML = $application." vient de récupérer un ".$type.": <a href=\"".$ddlObjPoster."\">".$ddlObjName."</a>"."\n\n";
        $HTML = $HTML."<b>Qualité \t Poids </b> \n";
        $HTML = $HTML.$ddlObjQuality." \t ".$ddlObjSize."\n\n";
        $HTML = $HTML."Date de téléchargement: <b>".$ddlObjDate."</b>\n\n";
        if ($caller == 'sonarr') {
            //$HTML = $HTML."Nombre d'épisodes manquants pour <b>".$ddlSerieName."</b>: ".$ddlObjMissingNumber;
        }
        return $HTML;
    }
    public function formatTitleImg($titleImg) {
        return $titleImg["title"]."\n".$titleImg["image"];
    }
}