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
require_once __DIR__  . '/sonarrApiWrapper.class.php';

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
			$info->setName(__('Dernier épisode téléchargé', __FILE__));
		}
		$info->setLogicalId('last_episode');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->save();	

      $info = $this->getCmd(null, 'notification');
		if (!is_object($info)) {
			$info = new sonarrCmd();
			$info->setName(__('Notification', __FILE__));
		}
		$info->setLogicalId('notification');
		$info->setEqLogic_id($this->getId());
		$info->setType('info');
		$info->setSubType('string');
		$info->save();	

      $info = $this->getCmd(null, 'monitoredSeries');
		if (!is_object($info)) {
			$info = new sonarrCmd();
			$info->setName(__('Séries monitorées', __FILE__));
		}
		$info->setLogicalId('monitoredSeries');
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
      log::add('sonarr', 'info', 'start REFRESH');
      $apiKey = $this->getConfiguration('apiKey');
      $url = $this->getConfiguration('sonarrUrl');
      $sonarrApiWrapper = new sonarrApiWrapper($url, $apiKey);
      $dayFutures = $this->getDayForFutures();
      log::add('sonarr', 'info', 'getting futures episodes for the the next '.$dayFutures.' days');
      $separator = $this->getSeparator();
      $futures_episodes = $sonarrApiWrapper->getFutureEpisodes($separator, $dayFutures);
      if ($futures_episodes == "") {
         log::add('sonarr', 'info', 'no future episodes');
      } else {
         $this->checkAndUpdateCmd('day_episodes', $futures_episodes); 
      }
      log::add('sonarr', 'info', 'getting missings episodes');
      $number = $this->getNumber();
      $liste_episode_missing = $sonarrApiWrapper->getMissingEpisodes($number, $separator);
      if ($liste_episode_missing == "") {
         log::add('sonarr', 'info', 'no missing episodes');
      } else {
         $this->checkAndUpdateCmd('day_missing_episodes', $liste_episode_missing); 
      }
      log::add('sonarr', 'info', 'getting last downloaded episodes');
      $liste_episode_history = $sonarrApiWrapper->getDownladedEpisodes($number, $separator);
      if ($liste_episode_history == "") {
         log::add('sonarr', 'info', 'no downloaded episodes');
      } else {
         $this->checkAndUpdateCmd('day_ddl_episodes', $liste_episode_history); 
      }
      log::add('sonarr', 'info', 'getting the last downloaded episode');
      $last_episode = $this->getCmd(null, 'last_episode')->execCmd();
      $sonarrApiWrapper->getLastDownloaded($last_episode, $number, $this);
      log::add('sonarr', 'info', 'getting the last downloaded episode with poster');
      $sonarrApiWrapper->getLastDownloadedImgs($last_episode, $number, $this);
      log::add('sonarr', 'info', 'getting all the monitored series');
      $liste_monitored_series = $sonarrApiWrapper->getMonitoredSeries($separator);
      if ($liste_monitored_series == "") {
         log::add('sonarr', 'info', 'no monitored series');
      } else {
         $this->checkAndUpdateCmd('monitoredSeries', $liste_monitored_series); 
      }
      log::add('sonarr', 'info', 'stop REFRESH');
   }
   public function getNumber() {
      $number = $this->getConfiguration('numberEpisodes');
      if (is_numeric($number) == true) {
         return $number;
      } else {
         return 5;
      }
   }
   public function getSeparator() {
      $separator = $this->getConfiguration('separator');
      if ($separator != NULL) {
         return $separator;
      } else {
         return ", ";
      }
   }
   public function getDayForFutures() {
      $dayFutures = $this->getConfiguration('dayFutureEpisodes');
      if ($dayFutures != NULL && is_numeric($dayFutures)) {
         return $dayFutures;
      } else {
         return 1;
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


