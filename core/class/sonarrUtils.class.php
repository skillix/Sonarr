<?php

require_once __DIR__  . '/Utils/LogSonarr.php';

class sonarrUtils
{

    public function getConfigurationFor($context, $numberDaysConfig, $numberMaxConfig)
    {
        $numberDays = $context->getConfiguration($numberDaysConfig);
        if ($numberDays == NULL || !is_numeric($numberDays)) {
            $numberDays = 1;
        }
        LogSonarr::info('Configuration for ' . $numberDaysConfig . ' is set to ' . $numberDays);
        $numberMax = $context->getConfiguration($numberMaxConfig);
        if ($numberMax == NULL && !is_numeric($numberMax)) {
            $numberMax = NULL;
            LogSonarr::info('Configuration for ' . $numberMaxConfig . ' not set, will use only day rule');
        } else {
            LogSonarr::info('Configuration for ' . $numberMaxConfig . ' is set to ' . $numberMax);
        }
        $rules = array(
            'numberDays' => $numberDays,
            'numberMax' => $numberMax,
        );
        return $rules;
    }
    public function applyMaxRulesToArray($arrayToFormat, $rules)
    {
        $numberMax = $rules["numberMax"];
        return sonarrUtils::applyMaxToArray($arrayToFormat, $numberMax);
    }
    public static function applyMaxToArray($arrayToFormat, $numberMax)
    {
        LogSonarr::debug('will return the ' . $numberMax . ' elements of the array');
        if ($numberMax != NULL && $numberMax < count($arrayToFormat)) {
            LogSonarr::debug('Need to reformat the array with max number');
            $formattedArray = [];
            for ($i = 0; $i < $numberMax; $i++) {
                array_push($formattedArray, $arrayToFormat[$i]);
            }
            return $formattedArray;
        } else {
            LogSonarr::debug('Number max to return is superior to the size of the array. Return the whole array');
            return $arrayToFormat;
        }
    }
    public function verifyJson($jsonToVerify)
    {
        $decodeJson = json_decode($jsonToVerify, true);
        if (array_key_exists('error', $decodeJson) && $decodeJson['error'] != NULL) {
            $msg = $decodeJson['error']['msg'];
            LogSonarr::error('There was an issue with the connection to Sonarr / Radarr :' . $msg);
            return NULL;
        }
        return $decodeJson;
    }
    public function getAnteriorDateForNumberDay($numberDays)
    {
        $anteriorDate = new DateTime();
        $anteriorDate->sub(new DateInterval('P' . $numberDays . 'D'));
        $anteriorDate = $anteriorDate->getTimestamp();
        return $anteriorDate;
    }
    public static function formatEpisode($episodeTitle, $seasonNumber, $episodeNumber, $formattor)
    {
        $utf8 = "UTF-8";
        $formatorSeason = "%s";
        $formatorEpisode = "%e";
        $posSeason = mb_strpos($formattor, $formatorSeason, 0, $utf8);
        $posEpisode = mb_strpos($formattor, $formatorEpisode, 0, $utf8);
        LogSonarr::debug('selected formattor ' . $formattor);
        if ($posSeason !== false && $posEpisode !== false) {
            LogSonarr::debug('found %s and %e in formattor');
            // We have season and episode formattor
            if ($posSeason < $posEpisode) {
                LogSonarr::debug('%s is before %e');
                // Season is before episode
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorSeason, 0, $utf8), $utf8);
                LogSonarr::debug('string for season formattor: ' . $seasonStr);
                $length = mb_strlen($seasonStr, $utf8) + mb_strlen($formatorSeason, $utf8);
                LogSonarr::debug('length of first part with formattor: ' . $length);
                $numberToTakeForEpisode = mb_strpos($formattor, $formatorEpisode, 0, $utf8) - $length;
                LogSonarr::debug('number to take for second part: ' . $numberToTakeForEpisode);
                $episodeStr = mb_substr($formattor, $length, $numberToTakeForEpisode, $utf8);
                LogSonarr::debug('string for episode formattor: ' . $episodeStr);
                return $episodeTitle . " " . $seasonStr . $seasonNumber . $episodeStr . $episodeNumber;
            } else {
                LogSonarr::debug('%s is after %e');
                // Episode is before season
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorEpisode, 0, $utf8), $utf8);
                LogSonarr::debug('string for episode formattor: ' . $episodeStr);
                $length = mb_strlen($episodeStr, $utf8) + mb_strlen($formatorEpisode, $utf8);
                LogSonarr::debug('length of first part with formattor: ' . $length);
                $numberToTakeForSeason = mb_strpos($formattor, $formatorSeason, 0, $utf8) - $length;
                LogSonarr::debug('number to take for second part: ' . $numberToTakeForSeason);
                $seasonStr = mb_substr($formattor, $length, $numberToTakeForSeason, $utf8);
                LogSonarr::debug('string for season formattor: ' . $seasonStr);
                return $episodeTitle . " " . $episodeStr . $episodeNumber . $seasonStr . $seasonNumber;
            }
        } else {
            if ($posSeason !== false) {
                LogSonarr::debug('only %s is present in: ' . $posSeason);
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorSeason, 0, $utf8), $utf8);
                return $episodeTitle . " " . $seasonStr . $seasonNumber;
            } else if ($posEpisode !== false) {
                LogSonarr::debug('only %e is present in: ' . $posEpisode);
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorEpisode, 0, $utf8), $utf8);
                return $episodeTitle . " " . $episodeStr . $episodeNumber;
            } else {
                LogSonarr::debug('no formattor');
                return $episodeTitle . " " . "S" . $seasonNumber . "E" . $episodeNumber;
            }
        }
    }

    public static function getEpisodesMoviesList($listObject, $groupEpisode, $formattor, $separatorEpisodes)
    {
        $listFormattedObject = [];
        if ($groupEpisode == 1) {
            LogSonarr::debug('Start group formatting for ' . count($listObject) . ' episodes');
            $newGroupedList = [];
            for ($i = 0; $i < count($listObject); $i++) {
                $object = $listObject[$i];
                $alreadyInList = false;
                $currentSerie = $object['serie'];
                $currentSeason = $object['season'];
                LogSonarr::debug('Will see if serie ' . $currentSerie . ' and season ' . $currentSeason . ' already in list');
                foreach ($newGroupedList as $fmtedObject) {
                    if (
                        $currentSerie == $fmtedObject['serie']
                        && $currentSeason == $fmtedObject['season']
                    ) {
                        LogSonarr::debug('Already in list');
                        $alreadyInList = true;
                    }
                }
                if ($alreadyInList == false) {
                    LogSonarr::debug('Not in list yet');
                    // We add in list
                    $fmtedObject = [];
                    $fmtedObject['serie'] = $currentSerie;
                    $fmtedObject['season'] = $currentSeason;
                    $fmtedObject['episodes'] = [$object['episode']];
                    $fmtedObject["image"] = $object["image"];
                    $fmtedObject["seriesId"] = $object['seriesId'];
                    $fmtedObject["date"] = $object["date"];
                    if ($i + 1 < count($listObject)) {
                        LogSonarr::debug('Episode is not the last one, we can look for other');
                        for ($j = $i + 1; $j < count($listObject); $j++) {
                            $nextObject = $listObject[$j];
                            // Check if next object is same serie and season number
                            if (
                                $currentSerie == $nextObject['serie']
                                && $currentSeason == $nextObject['season']
                            ) {
                                LogSonarr::debug('Other episode found for ' . $currentSerie);
                                array_push($fmtedObject['episodes'], $nextObject['episode']);
                            }
                        }
                    }
                    array_push($newGroupedList, $fmtedObject);
                }
            }
            LogSonarr::debug('All episodes are grouped now, there are ' . count($newGroupedList) . ' series');
            // We have our new formatted list
            // We can format this in titles
            foreach ($newGroupedList as $object) {
                // We format
                $object['title'] = sonarrUtils::formatGroupedEpisodesTitle($object, $formattor, $separatorEpisodes);
                LogSonarr::debug('Formatted title to add ' . $object['title']);
                array_push($listFormattedObject, $object);
            }
        } else {
            if ($formattor != null) {
                foreach ($listObject as $object) {
                    $formattedTitle = sonarrUtils::formatEpisode($object["serie"], $object["season"], $object["episode"], $formattor);
                    $object['title'] = $formattedTitle;
                    array_push($listFormattedObject, $object);
                }
            } else {
                return $listObject;
            }
        }
        return $listFormattedObject;
    }
    public static function formatGroupedEpisodesTitle($episodesToFormat, $formattor, $separatorEpisodes)
    {
        // First generate episode string
        $utf8 = "UTF-8";
        $formatorSeason = "%s";
        $formatorEpisode = "%e";
        $posSeason = mb_strpos($formattor, $formatorSeason, 0, $utf8);
        $posEpisode = mb_strpos($formattor, $formatorEpisode, 0, $utf8);
        LogSonarr::debug('selected formattor ' . $formattor);
        if ($posSeason !== false && $posEpisode !== false) {
            LogSonarr::debug('found %s and %e in formattor');
            // We have season and episode formattor
            if ($posSeason < $posEpisode) {
                LogSonarr::debug('%s is before %e');
                // Season is before episode
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorSeason, 0, $utf8), $utf8);
                LogSonarr::debug('string for season formattor: ' . $seasonStr);
                $length = mb_strlen($seasonStr, $utf8) + mb_strlen($formatorSeason, $utf8);
                LogSonarr::debug('length of first part with formattor: ' . $length);
                $numberToTakeForEpisode = mb_strpos($formattor, $formatorEpisode, 0, $utf8) - $length;
                LogSonarr::debug('number to take for second part: ' . $numberToTakeForEpisode);
                $episodeStr = mb_substr($formattor, $length, $numberToTakeForEpisode, $utf8);
                LogSonarr::debug('string for episode formattor: ' . $episodeStr);
                $episodeString = sonarrUtils::formatGroupedEpisodesStr($episodesToFormat, $episodeStr, $separatorEpisodes);
                return $episodesToFormat["serie"] . " " . $seasonStr . $episodesToFormat["season"] . $episodeString;
            } else {
                LogSonarr::debug('%s is after %e');
                // Episode is before season
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorEpisode, 0, $utf8), $utf8);
                LogSonarr::debug('string for episode formattor: ' . $episodeStr);
                $length = mb_strlen($episodeStr, $utf8) + mb_strlen($formatorEpisode, $utf8);
                LogSonarr::debug('length of first part with formattor: ' . $length);
                $numberToTakeForSeason = mb_strpos($formattor, $formatorSeason, 0, $utf8) - $length;
                LogSonarr::debug('number to take for second part: ' . $numberToTakeForSeason);
                $seasonStr = mb_substr($formattor, $length, $numberToTakeForSeason, $utf8);
                LogSonarr::debug('string for season formattor: ' . $seasonStr);
                $episodeString = sonarrUtils::formatGroupedEpisodesStr($episodesToFormat, $episodeStr, $separatorEpisodes);
                return $episodesToFormat["serie"] . " " . $episodeString . $seasonStr . $episodesToFormat["season"];
            }
        } else {
            if ($posSeason !== false) {
                LogSonarr::debug('only %s is present in: ' . $posSeason);
                $seasonStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorSeason, 0, $utf8), $utf8);
                return $episodesToFormat["serie"] . " " . $seasonStr . $episodesToFormat["season"];
            } else if ($posEpisode !== false) {
                LogSonarr::debug('only %e is present in: ' . $posEpisode);
                $episodeStr = mb_substr($formattor, 0, mb_strpos($formattor, $formatorEpisode, 0, $utf8), $utf8);
                $episodeString = sonarrUtils::formatGroupedEpisodesStr($episodesToFormat, $episodeStr, $separatorEpisodes);
            } else {
                LogSonarr::debug('no formattor');
                $episodeString = "";
                foreach ($episodesToFormat as $episode) {
                    $episodeString = $episodeString . "E" . $episode["episode"];
                }
                return $episodesToFormat["serie"] . " " . "S" . $episodesToFormat["season"] . $episodeString;
            }
        }
    }
    public static function formatGroupedEpisodesStr($episodesToFormat, $episodeStr, $separatorEpisodes)
    {
        $episodeNumberList = [];
        foreach ($episodesToFormat['episodes'] as $numberEpisode) {
            array_push($episodeNumberList, $numberEpisode);
        }
        return $episodeStr . implode($separatorEpisodes, $episodeNumberList);
    }

    public function formatList($list, $episode, $separator)
    {
        if ($list == "") {
            $list = $episode;
        } else {
            $list = $list . $separator . $episode;
        }
        return $list;
    }
    public function sendNotificationForTitleImgArray($caller, $titleImgArray, $context)
    {
        // Reverse Array to be asc
        $titleImgArray = array_reverse($titleImgArray);
        LogSonarr::info("will send notification for " . count($titleImgArray) . " movies/series");
        foreach ($titleImgArray as $titleImg) {
            $formattedTitle = $this->formatTitleImg($titleImg);
            LogSonarr::info("send notification for " . $formattedTitle);
            $context->getCmd(null, 'notification')->event($formattedTitle);
            $context->getCmd(null, 'last_episode')->event($titleImg["title"]);
            $notificationHTML = $this->formatHTMLNotification($caller, $titleImg["title"], $titleImg["quality"], $titleImg["size"], $titleImg["date"], $titleImg["image"]);
            $context->getCmd(null, 'notificationHTML')->event($notificationHTML);
            sleep(1);
        }
    }
    public function formatDate($ddlObjDate)
    {
        $ddlObjDateFmatted = strtotime($ddlObjDate);
        $date = new DateTime();
        $date->setTimestamp($ddlObjDateFmatted);
        $ddlObjDateFmatted = $date->format('d/m/Y H:i:s');
        return $ddlObjDateFmatted;
    }
    public function formatSize($sizeToFormat)
    {
        $convertGib = 1073741824;
        $convertMib = 1048576;
        $sizeGib = $sizeToFormat / $convertGib;
        if ($sizeGib >= 1) {
            // Convert to GigaByte
            return round(($sizeGib), 2) . "GB";
        } else {
            // Convert to MegaByte
            return round(($sizeToFormat / $convertMib), 2) . "MB";
        }
    }
    private function formatHTMLNotification($caller, $ddlObjName, $ddlObjQuality, $ddlObjSize, $ddlObjDate, $ddlObjPoster)
    {
        if ($caller == 'sonarr') {
            $application = "Sonarr";
            $type = __("nouvel épisode", __FILE__);
        } else if ($caller == 'radarr') {
            $application = "Radarr";
            $type = __("nouveau film", __FILE__);
        }
        $HTML = $application . " " . __("vient de récupérer un ", __FILE__) . $type . ": <a href=\"" . $ddlObjPoster . "\">" . $ddlObjName . "</a>" . "\n\n";
        if ($ddlObjQuality != '' && $ddlObjSize != '') {
            $HTML = $HTML . "<b>" . __("Qualité", __FILE__) . " \t " . __("Poids", __FILE__) . " </b> \n";
            $HTML = $HTML . $ddlObjQuality . " \t " . $ddlObjSize . "\n\n";
        } else if ($ddlObjQuality != '') {
            $HTML = $HTML . "<b>" . __("Qualité", __FILE__) . "</b> \n";
            $HTML = $HTML . $ddlObjQuality . "\n\n";
        } else if ($ddlObjSize != '') {
            $HTML = $HTML . "<b>" . __("Poids", __FILE__) . "</b> \n";
            $HTML = $HTML . $ddlObjSize . "\n\n";
        }
        if ($ddlObjDate != '') {
            $HTML = $HTML . __("Date de téléchargement", __FILE__) . ": <b>" . $ddlObjDate . "</b>\n\n";
        }
        return $HTML;
    }
    public function formatTitleImg($titleImg)
    {
        return $titleImg["title"] . "\n" . $titleImg["image"];
    }
}
