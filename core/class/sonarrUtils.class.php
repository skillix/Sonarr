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
    public function formatEpisodeImg($episodeImg) {
        return $episodeImg["episode"]."\n".$episodeImg["image"];
    }
}