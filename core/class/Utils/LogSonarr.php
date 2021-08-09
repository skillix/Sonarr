<?php
class LogSonarr
{
	public static function info($str) {
        log::add('sonarr', 'info', $str);
    }
    public static function error($str) {
        log::add('sonarr', 'error', $str);
    }
    public static function debug($str) {
        log::add('sonarr', 'debug', $str);
    }
}



