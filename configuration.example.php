<?php
    
    class Configuration
    {

        //Should the generated query be checked for errors? (valid database connection is required)
        public $checkDatabase = true;

        public $db = [
            
            'host' => 'vm-dev',
            'database' => 'twm_latest',
            'user' => 'root',
            'password' => 'XXXXX',

        ];
       

    }
    
    $Configuration = new Configuration();

