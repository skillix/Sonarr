<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/sonarrApi.class.php';

class sonarr extends eqLogic {
        
   public static function cron() {
      foreach (self::byType('sonarr', true) as $sonarr) {
          if ($sonarr->getIsEnable() != 1) continue;
          $autorefresh = $sonarr->getConfiguration('autorefresh');
          if ($autorefresh == '')  continue;
          try {
              $cron = new Cron\CronExpression($autorefresh, new Cron\FieldFactory);
              if ($cron->isDue()) {
                  $sonarr->refresh();
              }
          } catch (Exception $e) {
              log::add('sonarr', 'error', __('Expression cron non valide pour ', __FILE__) . $sonarr->getHumanName() . ' : ' . $autorefresh);
          }
      }
   }

    public function postSave() {
      $info = $this->getCmd(null, 'day_ddl_episodes');
		if (!is_object($info)) {
			$info = new sonarrCmd();
			$info->setName(__('Episodes téléchargés', __FILE__));
		}
		$info->setLogicalId('day_ddl_episodes');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->save();	

      $info = $this->getCmd(null, 'day_episodes');
		if (!is_object($info)) {
			$info = new sonarrCmd();
			$info->setName(__('Episodes futures', __FILE__));
		}
		$info->setLogicalId('day_episodes');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->save();	

      $info = $this->getCmd(null, 'day_missing_episodes');
		if (!is_object($info)) {
			$info = new sonarrCmd();
			$info->setName(__('Episodes manquants', __FILE__));
		}
		$info->setLogicalId('day_missing_episodes');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->save();	

      $info = $this->getCmd(null, 'last_episode');
		if (!is_object($info)) {
			$info = new sonarrCmd();
			$info->setName(__('Dernier épisode', __FILE__));
		}
		$info->setLogicalId('last_episode');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->save();	
		
		$refresh = $this->getCmd(null, 'refresh');
		if (!is_object($refresh)) {
			$refresh = new sonarrCmd();
			$refresh->setName(__('Rafraichir', __FILE__));
		}
		$refresh->setEqLogic_id($this->getId());
		$refresh->setLogicalId('refresh');
		$refresh->setType('action');
		$refresh->setSubType('other');
		$refresh->save(); 
    }
   public function refresh() {
      // Get Calendar
      $calendar = $this->getCalendar();
      // Get all episode for the day
      $liste_episode_day = $this->getEpisodes($calendar); 	
		$this->checkAndUpdateCmd('day_episodes', $liste_episode_day); 
      $liste_episode_missing = $this->getMissing();
      $this->checkAndUpdateCmd('day_missing_episodes', $liste_episode_missing); 
      $liste_episode_history = $this->getDownladedEpisodes();
      $this->checkAndUpdateCmd('day_ddl_episodes', $liste_episode_history); 
      $this->getLastDownloaded();
   }
   public function getNumber() {
      $number = $this->getConfiguration('numberEpisodes');
      if (is_numeric($number) == true) {
         return $number;
      } else {
         return 5;
      }
   }
    public function getCalendar() {
      $apiKey = $this->getConfiguration('apiKey');
      $sonarrUrl = $this->getConfiguration('sonarrUrl');
      $liste_episode = "";
      $sonarrApi = new sonarrApi($sonarrUrl, $apiKey);
      $calendar = $sonarrApi->getCalendar();

      return json_decode($calendar, true);
    }
    public function getEpisodes($calendar) {
      $liste_episode = "";
      foreach($calendar as $serie) {
         $episodeTitle = $serie["series"]["title"];
         $seasonNumber = $serie["seasonNumber"];
         $episodeNumber = $serie["episodeNumber"];
         $episode = $this->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber);
         $liste_episode = $this->formatList($liste_episode, $episode);
      }
      return $liste_episode;
    }
    public function getMissing() {
      $apiKey = $this->getConfiguration('apiKey');
      $sonarrUrl = $this->getConfiguration('sonarrUrl');
      $number = $this->getNumber();
      $liste_episode = "";
      $sonarrApi = new sonarrApi($sonarrUrl, $apiKey);
      $missingEpisodesJSON = $sonarrApi->getWantedMissing(1, $number, 'airDateUtc', 'desc');
      $missingEpisodes = json_decode($missingEpisodesJSON, true);
      foreach($missingEpisodes['records'] as $serie) {
         $episodeTitle = $serie["series"]["title"];
         $seasonNumber = $serie["seasonNumber"];
         $episodeNumber = $serie["episodeNumber"];
         $episode = $this->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber);
         $liste_episode = $this->formatList($liste_episode, $episode);
      }
      return $liste_episode;
    }
    public function getHistory() {
      $apiKey = $this->getConfiguration('apiKey');
      $sonarrUrl = $this->getConfiguration('sonarrUrl');
      $number = $this->getNumber();
      $numberMax = $number * 4;
      $liste_episode = [];
      $sonarrApi = new sonarrApi($sonarrUrl, $apiKey);
      $historyJSON = $sonarrApi->getHistory(1, $numberMax, 'date', 'desc');
      $history = json_decode($historyJSON, true);
      foreach($history['records'] as $serie) {
         if (count($liste_episode) < $number && strcmp($serie["eventType"] , "downloadFolderImported") == 0) {
            $episodeTitle = $serie["series"]["title"];
            $seasonNumber = $serie["episode"]["seasonNumber"];
            $episodeNumber = $serie["episode"]["episodeNumber"];
            $episode = $this->formatEpisode($episodeTitle, $seasonNumber, $episodeNumber);
            array_push($liste_episode, $episode);
         }
      }
      return $liste_episode;
    }
    public function getDownladedEpisodes() {
       $list_episodes = $this->getHistory();
       return implode(", ",$list_episodes);
    }
    public function formatEpisode($episodeTitle, $seasonNumber, $episodeNumber) {   
      $formatted = $episodeTitle." S".$seasonNumber."E".$episodeNumber;
      return $formatted;
    }
    public function formatList($list, $episode) {
      if ($list == "") {
         $list = $episode;
      } else {
         $list = $list.", ".$episode;
      }
      return $list;
    }
    public function getLastDownloaded() {
      $last_episode = $this->getCmd(null, 'last_episode')->execCmd();
      $list_episodes = $this->getHistory();
      $list_episodes = array_reverse($list_episodes);
      log::add('scenario', 'info', "size array ".count($list_episodes));
      if (array_search($last_episode, $list_episodes, true) === false) {
         log::add('scenario', 'info', "No notification has been sent yet");
         foreach($list_episodes as $episode) {
            log::add('scenario', 'info', "send notif for ".$episode);
            $this->getCmd(null, 'last_episode')->event($episode);
         }
      } else {
         log::add('scenario', 'info', "check if need notification");
         $position = array_search($last_episode, $list_episodes, true);
         if ($position != (count($list_episodes) - 1)) {
            log::add('scenario', 'info', "need notification");
            for ($i = $position; $i < count($list_episodes); $i++) {
               log::add('scenario', 'info', "send notif for ".$list_episodes[$i]);
               $this->getCmd(null, 'last_episode')->event($list_episodes[$i]);
           }
         }
      }
    }
}

class sonarrCmd extends cmd {
  // Exécution d'une commande  
   public function execute($_options = array()) {
      $eqlogic = $this->getEqLogic();
		switch ($this->getLogicalId()) { 			
			case 'refresh': 
				$eqlogic->refresh();
            break;
		}
   }
}


