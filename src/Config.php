<?php

    use PITS\Config\Live\Configuration as LiveConfiguration;

    /**
     * Class Config
     */
    class Config
    {

        //Aktuelle Config (merge configuration.php und configuration_local.php)
        public static $config = null;

        //Lokale Config
        public static $config_local = null;
        


        //wurde die live config oder local config geladen? local oder live
        private static $environment = null;

        public static function setConfig($config)
        {
            self::$config = $config;
        }


        /**
         * Get Config entry from current loaded configuration file
         *
         * @param      $key
         * @param null $defaultValue
         *
         * @return mixed
         * @throws Exception
         */
        public static function get($key, $defaultValue = null)
        {
            if (self::$config === null) {
                throw new Exception("No Configuration file was loaded");
            }

            if (property_exists(self::$config, $key)) {
                return self::$config->$key;
            }
            //Property not found in config, return default value:
            return $defaultValue;
        }


        /**
         * Config-Wert vorübergehend ändern
         * @param      $key
         * @param null $value
         */
        public static function set($key, $value = null)
        {
            self::$config->$key = $value;
        }

        /**
         * Aktuell geladene Config
         * @return null
         */
        public static function getConfig()
        {
            return self::$config;
        }

        /**
         * Lade App Config
         * Lädt IMMER die configuration.php - existiert die _local Version, wird diese auch geladen und die Werte
         * aus der Live Config überschrieben
         */
        public static function loadConfig()
        {
            $config_file_live = __DIR__ . "/../configuration.php";
            $config_file_local = __DIR__ . "/../configuration_local.php";

            if(file_exists($config_file_local)){
                self::setEnvironment('local');
                include($config_file_local);
            }else{
                self::setEnvironment('live');

                if(!file_exists($config_file_live)){
                    die("configuration.php is missing! Copy configuration.example.php!");
                }

                include($config_file_live);

            }

            self::setConfig($Configuration);

        }

        public static function init()
        {
            self::loadConfig();
        }

        /**
         * @return null
         */
        public static function getEnvironment ()
        {
            return self::$environment;
        }

        /**
         * @param null $environment
         */
        private static function setEnvironment ($environment): void
        {
            self::$environment = $environment;
        }
    }

Config::init();