<?php
class sonarrUtils {
    public function verifyJson($jsonToVerify) {
        $decodeJson = json_decode($jsonToVerify, true);
        if ($decodeJson['error'] != NULL) {
           $msg = $decodeJson['error']['msg'];
           log::add('sonarr', 'warning', 'There was an issue with the connection to Sonarr :'.$msg);
           return NULL;
        }
        return $decodeJson;
    }

    public function formatEpisode($episodeTitle, $seasonNumber, $episodeNumber) {   
        $formatted = $episodeTitle." S".$seasonNumber."E".$episodeNumber;
        return $formatted;
    }

    public function getEpisodesList($episodesImgs) {
        $list_episode = [];
        foreach($episodesImgs as $episodeImg) {
            array_push($list_episode, $episodeImg["episode"]);
        }
        return $list_episode;
    }

    public function formatList($list, $episode, $separator) {
        if ($list == "") {
           $list = $episode;
        } else {
           $list = $list.$separator.$episode;
        }
        return $list;
    }
    public function sendNotificationForTitleImgArray($titleImgArray, $context) {
        // Reverse Array to be asc
        $titleImgArray = array_reverse($titleImgArray);
        log::add('sonarr', 'info', "will send notification for ".count($titleImgArray)." movies/series");
        foreach($titleImgArray as $titleImg) {
            $formattedTitle = $this->formatTitleImg($titleImg);
            log::add('sonarr', 'info', "send notification for ".count($formattedTitle));
            $context->getCmd(null, 'notification')->event($formattedTitle);
            $context->getCmd(null, 'last_episode')->event($titleImg["title"]);
            sleep(1);
        }
    }
    public function formatTitleImg($titleImg) {
        return $titleImg["title"]."\n".$titleImg["image"];
    }
}