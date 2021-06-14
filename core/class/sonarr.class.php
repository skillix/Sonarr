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
require_once __DIR__  . '/radarrApiWrapper.class.php';
require_once __DIR__ . '/../../vendor/mips/jeedom-tools/src/MipsTrait.php';

class sonarr extends eqLogic {
   use MipsTrait;

   public function getImage() {
      $application = $this->getConfiguration('application', '');
      if ($application == 'sonarr') {
         return 'plugins/sonarr/plugin_info/sonarr.png';
      } else if ($application == 'radarr') {
         return 'plugins/sonarr/plugin_info/radarr.png';
      }
	}

   public static function getApplications() {
      $return = array(
          'sonarr' => 'Sonarr',
          'radarr' => 'Radarr'
      );
      return $return;
  }

  private function removeUnusedCommands($commandsDef) {
      foreach ($this->getCmd() as $cmd) {
         if (!in_array($cmd->getLogicalId(), array_column($commandsDef, 'logicalId'))) {
            log::add(__CLASS__, 'debug', "Removing {$cmd->getLogicalId()}");
            $cmd->remove();
         }
      }
   }

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
      $commands = self::getCommandsFileContent(__DIR__ . '/../config/commands.json');

      $application = $this->getConfiguration('application', '');
      if ($application == '') {
         $this->removeUnusedCommands(array());
      } else {
         $this->removeUnusedCommands($commands[$application]);
         $this->createCommandsFromConfig($commands[$application]);
      }
    }

   public function refresh() {
      $application = $this->getConfiguration('application', '');
      if ($application == '') {
         log::add('sonarr', 'info', 'impossible to refresh no application set. You have to set Sonarr or Radarr');
      } else {
         if ($application == 'sonarr') {
            $this->refreshSonarr();
         } else if ($application == 'radarr') {
            $this->refreshRadarr();
         }
      }
   }
   private function refreshSonarr() {
      log::add('sonarr', 'info', 'start REFRESH SONARR');
      $apiKey = $this->getConfiguration('apiKey');
      $url = $this->getConfiguration('sonarrUrl');
      $sonarrApiWrapper = new sonarrApiWrapper($url, $apiKey);
      $dayFutures = $this->getDayForFuturesEpisode();
      log::add('sonarr', 'info', 'getting futures episodes for the the next '.$dayFutures.' days');
      $separator = $this->getSeparator();
      $futures_episodes = $sonarrApiWrapper->getFutureEpisodes($separator, $dayFutures);
      if ($futures_episodes == "") {
         log::add('sonarr', 'info', 'no future episodes');
      } else {
         $this->checkAndUpdateCmd('day_episodes', $futures_episodes); 
      }
      log::add('sonarr', 'info', 'getting missings episodes');
      $number = $this->getNumberEpisode();
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
      log::add('sonarr', 'info', 'notify for last downloaded episodes');
      $last_refresh_date = $this->getCmd(null, 'last_episode')->getValueDate();
      $sonarrApiWrapper->notifyEpisode($last_refresh_date, $this);
      log::add('sonarr', 'info', 'getting the monitored series');
      $liste_monitored_series = $sonarrApiWrapper->getMonitoredSeries($separator);
      if ($liste_monitored_series == "") {
         log::add('sonarr', 'info', 'no monitored series');
      } else {
         $this->checkAndUpdateCmd('monitoredSeries', $liste_monitored_series); 
      }
      log::add('sonarr', 'info', 'stop REFRESH SONARR');
   }
   private function getNumberEpisode() {
      $number = $this->getConfiguration('numberEpisodes');
      if (is_numeric($number) == true) {
         return $number;
      } else {
         return 5;
      }
   }
   private function getDayForFuturesEpisode() {
      $dayFutures = $this->getConfiguration('dayFutureEpisodes');
      if ($dayFutures != NULL && is_numeric($dayFutures)) {
         return $dayFutures;
      } else {
         return 1;
      }
   }
   private function refreshRadarr() {
      log::add('sonarr', 'info', 'start REFRESH RADARR');
      $apiKey = $this->getConfiguration('apiKey');
      $url = $this->getConfiguration('radarrUrl');
      $radarrApiWrapper = new radarrApiWrapper($url, $apiKey);
      $dayFutures = $this->getDayForFuturesMovie();
      log::add('sonarr', 'info', 'getting futures movies for the the next '.$dayFutures.' days');
      $separator = $this->getSeparator();
      $futures_movies = $radarrApiWrapper->getFutureMovies($separator, $dayFutures);
      if ($futures_movies == "") {
         log::add('sonarr', 'info', 'no future movies');
      } else {
         $this->checkAndUpdateCmd('day_movies', $futures_movies); 
      }
      log::add('sonarr', 'info', 'getting all the missings movies');
      $liste_movies_missing = $radarrApiWrapper->getMissingMovies($separator);
      if ($liste_movies_missing == "") {
         log::add('sonarr', 'info', 'no missing movies');
      } else {
         $this->checkAndUpdateCmd('day_missing_movies', $liste_movies_missing); 
      }
      $number = $this->getNumberEpisode();
      log::add('sonarr', 'info', 'getting last downloaded movies');
      $liste_movies_history = $radarrApiWrapper->getDownladedMovies($number, $separator);
      if ($liste_movies_history == "") {
         log::add('sonarr', 'info', 'no downloaded movies');
      } else {
         $this->checkAndUpdateCmd('day_ddl_movies', $liste_movies_history); 
      }
      log::add('sonarr', 'info', 'notify for last downloaded movies');
      $last_refresh_date = $this->getCmd(null, 'last_episode')->getValueDate();
      $radarrApiWrapper->notifyMovie($last_refresh_date, $this);
      log::add('sonarr', 'info', 'stop REFRESH RADARR');
   }
   private function getDayForFuturesMovie() {
      $dayFutures = $this->getConfiguration('dayFutureMovies');
      if ($dayFutures != NULL && is_numeric($dayFutures)) {
         return $dayFutures;
      } else {
         return 1;
      }
   }
   private function getNumberMovies() {
      $number = $this->getConfiguration('numberMovies');
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


