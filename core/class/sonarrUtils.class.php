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
    public function applyMaxRulesToArray($arrayToFormat, $rules) {
        $numberMax = $rules["numberMax"];
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
        $posSeason = mb_strpos($formattor, "%s", 0, "UTF-8");
        $posEpisode = mb_strpos($formattor, "%e", 0, "UTF-8");
        log::add('sonarr', 'debug', 'selected formattor '.$formattor);
        if ($posSeason !== false && $posEpisode !== false) {
            log::add('sonarr', 'debug', 'found %s and %e in formattor');
            // We have season and episode formattor
            if ($posSeason < $posEpisode) {
                log::add('sonarr', 'debug', '%s is before %e');
                // Season is before episode
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, "%s", 0, "UTF-8"), "UTF-8");
                $episodeStr = mb_substr($formattor, (mb_strlen($seasonStr, "UTF-8") + 2), (mb_strlen($formattor, "UTF-8") - mb_strlen($seasonStr, "UTF-8") - 4), "UTF-8");
                return $episodeTitle." ".$seasonStr.$seasonNumber.$episodeStr.$episodeNumber;
            } else {
                log::add('sonarr', 'debug', '%s is after %e');
                // Episode is before season
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, "%e", 0, "UTF-8"), "UTF-8");
                $seasonStr = mb_substr($formattor, (mb_strlen($episodeStr, "UTF-8") + 2), (mb_strlen($formattor, "UTF-8") - mb_strlen($episodeStr, "UTF-8") - 4), "UTF-8");
                return $episodeTitle." ".$episodeStr.$episodeNumber.$seasonStr.$seasonNumber;
            }
        } else {
            if ($posSeason !== false) {
                log::add('sonarr', 'debug', 'only %s is present in: '.$posSeason);
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, "%s", 0, "UTF-8"), "UTF-8");
                return $episodeTitle." ".$seasonStr.$seasonNumber;
            } else if ($posEpisode !== false) {
                log::add('sonarr', 'debug', 'only %e is present in: '.$posEpisode);
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, "%e", 0, "UTF-8"), "UTF-8");
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
            $notificationHTML = $this->formatHTMLNotification($caller, $titleImg["title"], $titleImg["quality"], $titleImg["size"], $titleImg["ddlDate"], $titleImg["serie"], $titleImg["missingEpNumber"], $titleImg["image"]);
            $context->getCmd(null, 'notificationHTML')->event($notificationHTML);
            sleep(1);
        }
    }
    private function formatHTMLNotification($caller, $ddlObjName, $ddlObjQuality, $ddlObjSize, $ddlObjDate, $ddlSerieName, $ddlObjMissingNumber, $ddlObjPoster) {
        $ddlObjDateFmatted = strtotime($ddlObjDate);
        $date = new DateTime();
        $date->setTimestamp($ddlObjDateFmatted);
        $ddlObjDateFmatted = $date->format('Y-m-d H:i:s');
        if ($caller == 'sonarr') {
            $application = "Sonarr";
            $type = "nouvel épisode";
        } else if ($caller == 'radarr') {
            $application = "Radarr";
            $type = "nouveau film";
        }
        $HTML = $application." vient de récupérer un ".$type.": <a href=\"".$ddlObjPoster."\">".$ddlObjName."</a>"."\n\n";
        $HTML = $HTML."<b>Qualité \t Poids </b> \n";
        $HTML = $HTML.$ddlObjQuality."p \t ".$ddlObjSize."\n\n";
        $HTML = $HTML."Date de téléchargement: <b>".$ddlObjDateFmatted."</b>\n\n";
        if ($caller == 'sonarr') {
            //$HTML = $HTML."Nombre d'épisodes manquants pour <b>".$ddlSerieName."</b>: ".$ddlObjMissingNumber;
        }
        return $HTML;
    }
    public function formatTitleImg($titleImg) {
        return $titleImg["title"]."\n".$titleImg["image"];
    }
}