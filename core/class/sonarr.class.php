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
require_once __DIR__  . '/sonarrUtils.class.php';
require_once __DIR__ . '/../../vendor/mips/jeedom-tools/src/MipsTrait.php';

class sonarr extends eqLogic {
   use MipsTrait;
   protected $utils;

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
      // Init variables
      $this->utils = new sonarrUtils();
      // Refresh datas
      $application = $this->getConfiguration('application', '');
      if ($application == '') {
         log::add('sonarr', 'info', 'impossible to refresh no application set. You have to set Sonarr or Radarr');
      } else {
         if ($application == 'sonarr') {
            $this->refreshSonarr($application);
         } else if ($application == 'radarr') {
            $this->refreshRadarr($application);
         }
      }
   }
   private function refreshSonarr($application) {
      log::add('sonarr', 'info', 'start REFRESH SONARR');
      $apiKey = $this->getConfiguration('apiKey');
      $url = $this->getConfiguration('sonarrUrl');
      $sonarrApiWrapper = new sonarrApiWrapper($url, $apiKey);
      $separator = $this->getSeparator();
      log::add('sonarr', 'info', 'selected separator: '.$separator);
      $formattor = $this->getConfiguration('formattorEpisode');
      log::add('sonarr', 'info', 'selected formattor: '.$formattor);
      log::add('sonarr', 'info', 'getting futures episodes, will look for selected rule');
      $futurEpisodesRules = $this->utils->getConfigurationFor($this, "dayFutureEpisodes", "maxFutureEpisodes");
      $futurEpisodeList = $sonarrApiWrapper->getFutureEpisodes($separator, $futurEpisodesRules, $formattor);
      if ($futurEpisodeList == "") {
         log::add('sonarr', 'info', 'no future episodes');
      }
      $this->checkAndUpdateCmd('day_episodes', $futurEpisodeList); 
      log::add('sonarr', 'info', 'getting missings episodes, will look for selected rule');
      $missingEpisodesRules = $this->utils->getConfigurationFor($this, "dayMissingEpisodes", "maxMissingEpisodes");
      $missingEpisodesList = $sonarrApiWrapper->getMissingEpisodes($missingEpisodesRules, $separator, $formattor);
      if ($missingEpisodesList == "") {
         log::add('sonarr', 'info', 'no missing episodes');
      }
      $this->checkAndUpdateCmd('day_missing_episodes', $missingEpisodesList); 
      log::add('sonarr', 'info', 'getting last downloaded episodes, will look for specific rules');
      $downloadedEpisodesRules = $this->utils->getConfigurationFor($this, "dayDownloadedEpisodes", "maxDownloadedEpisodes");
      $dowloadedEpisodesList = $sonarrApiWrapper->getDownladedEpisodes($downloadedEpisodesRules, $separator, $formattor);
      if ($dowloadedEpisodesList == "") {
         log::add('sonarr', 'info', 'no downloaded episodes');
      }
      $this->checkAndUpdateCmd('day_ddl_episodes', $dowloadedEpisodesList);
      log::add('sonarr', 'info', 'notify for last downloaded episodes');
      $last_refresh_date = $this->getCmd(null, 'last_episode')->getValueDate();
      $sonarrApiWrapper->notifyEpisode($application, $last_refresh_date, $this, $formattor);
      log::add('sonarr', 'info', 'getting the monitored series');
      $liste_monitored_series = $sonarrApiWrapper->getMonitoredSeries($separator);
      if ($liste_monitored_series == "") {
         log::add('sonarr', 'info', 'no monitored series');
      } else {
         $this->checkAndUpdateCmd('monitoredSeries', $liste_monitored_series); 
      }
      log::add('sonarr', 'info', 'stop REFRESH SONARR');
   }

   private function refreshRadarr($application) {
      log::add('sonarr', 'info', 'start REFRESH RADARR');
      $apiKey = $this->getConfiguration('apiKey');
      $url = $this->getConfiguration('radarrUrl');
      $radarrApiWrapper = new radarrApiWrapper($url, $apiKey);
      $separator = $this->getSeparator();
      log::add('sonarr', 'info', 'selected separator: '.$separator);
      log::add('sonarr', 'info', 'getting futures movies, will look for selected rule');
      $futurMoviesRules = $this->utils->getConfigurationFor($this, "dayFutureMovies", "maxFutureMovies");
      $futurMovieList = $radarrApiWrapper->getFutureMovies($separator, $futurMoviesRules);
      if ($futurMovieList == "") {
         log::add('sonarr', 'info', 'no future movies');
      }
      $this->checkAndUpdateCmd('day_movies', $futurMovieList); 
      log::add('sonarr', 'info', 'getting missings movies');
      $missingMoviesList = $radarrApiWrapper->getMissingMovies($separator);
      if ($missingMoviesList == "") {
         log::add('sonarr', 'info', 'no missing movies');
      }
      $this->checkAndUpdateCmd('day_missing_movies', $missingMoviesList); 
      log::add('sonarr', 'info', 'getting last downloaded movies, will look for selected rules');
      $downloadMoviesRules = $this->utils->getConfigurationFor($this, "dayDownloadedMovies", "maxDownloadedMovies");
      $downloadMoviesList = $radarrApiWrapper->getDownladedMovies($downloadMoviesRules, $separator);
      if ($downloadMoviesList == "") {
         log::add('sonarr', 'info', 'no downloaded movies');
      }
      $this->checkAndUpdateCmd('day_ddl_movies', $downloadMoviesList); 
      log::add('sonarr', 'info', 'notify for last downloaded movies');
      $last_refresh_date = $this->getCmd(null, 'last_episode')->getValueDate();
      $radarrApiWrapper->notifyMovie($application, $last_refresh_date, $this);
      log::add('sonarr', 'info', 'stop REFRESH RADARR');
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


