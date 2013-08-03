<?php namespace T4s\CamelotAuth;

use T4s\CamelotAuth\Auth;
use T4s\CamelotAuth\Database\DatabaseInterface;
use T4s\CamelotAuth\Config\ConfigInterface;
use T4s\CamelotAuth\Session\SessionInterface;
use T4s\CamelotAuth\Cookie\CookieInterface;
use T4s\CamelotAuth\Events\DispatcherInterface;

class Camelot{

    /**
    * The Session Driver used by Camelot
    *
    * @var use T4s\CamelotAuth\Session\SessionInterface;
    */
    protected $session;

    /**
    * The Cookie Driver used by Camelot
    *
    * @var use T4s\CamelotAuth\Cookie\CookieInterface;
    */
    protected $cookie;

    /**
     * The Database Driver
     *
     * @var T4s\CamelotAuth\Database\DatabaseInterface
     */
    protected $database;

    /**
     * The Config driver
     *
     * @var T4s\CamelotAuth\Config\ConfigInterface
     */
    protected $config;

    /**
    * The event dispatcher instance.
    *
    * @var T4s\CamelotAuth\Events\DispatcherInterface;
    */
    protected $events;

    /**
     * A list of supported drivers 
     *
     * @var array
     */
    protected $supported_drivers = array();

    /**
     * The http Path
     *
     * @var string
     */
    protected $httpPath;

    /**
     * Loaded Authentication Driver.
     *
     * @var T4s\CamelotAuth\AuthDriver\CamelotDriver
     */
    protected $driver = null;


    public function __construct(SessionInterface $session,CookieInterface $cookie,ConfigInterface $config,$httpPath)
    {
        $this->session = $session;
        $this->cookie = $cookie;
        $this->config = $config;
        $this->httpPath = $httpPath;
        $this->supported_drivers = $this->config->get('camelot.provider_routing');   

        $this->database = $this->loadDatabaseDriver($this->config->get('camelot.database_driver'));


        $this->session->put($this->session->get('current_url'),'previous_url');
        $this->session->put($this->httpPath,'current_url');    
    }

    public function loadDriver($driverName = null,$provider = null)
    {
        // there is no driver specified lets try and detect the required driver
        if(is_null($driverName))
        {
            // if detect_provider == true 
            if($this->config->get('camelot.detect_provider'))
            {
                $segments = explode("/", $this->httpPath);

                if(isset($segments[$this->config->get('camelot.route_location')-1]))
                {
                    $provider = $segments[$this->config->get('camelot.route_location')-1];
               
                    if(isset($this->supported_drivers[ucfirst($provider)]))
                    {
                       $driverName = $this->supported_drivers[ucfirst($provider)]['driver'];
                    }
                }
            }

            // if the driver is still null lets just load the default driver
            if(is_null($driverName))
            {
                $driverName = $this->config->get('camelot.default_driver');
            }
        }
        
        // lets load the specified driver
        $driverFile = __DIR__.'/Auth/'.ucfirst($driverName).'Auth.php';
        if(!file_exists($driverFile))
        {
            throw new \Exception("Cannot Find the ".ucfirst($driverName)." Driver");
        }
        include_once $driverFile;
        
        $driverClass ='T4s\CamelotAuth\Auth\\'.ucfirst($driverName).'Auth';
        if(!class_exists($driverClass,false))
        {
            throw new \Exception("Cannot Find Driver class (".$driverClass.")");
        }
        // are there config settings set for this driver if not set it to blank
        if(!isset($this->supported_drivers[ucfirst($provider)]['config']))
        {
            $this->config->get('camelot.provider_routing')[ucfirst($provider)]['config'] = array();
        }

       
        $this->driver =  new $driverClass(
                $this->config,
                $this->session,
                $this->cookie,
                $this->database,
                $provider,
                
                $this->httpPath
                );

        if(isset($this->events))
        {
            $this->driver->setEventDispatcher($this->events);
        }

        return $this->driver;
    }

    public function __call($method,$params)
    {      
        if(isset($params[0]) && is_string($params[0]) && isset($this->supported_drivers[ucfirst($params[0])]))
        {                
                $driver = $this->loadDriver($this->supported_drivers[ucfirst($params[0])]['driver']);
                echo $params[0];
        }

        if(!isset($driver) || is_null($driver)) 
        {
            if(is_null($this->driver))
            {
               $this->driver = $this->loadDriver();             
            }  
             $driver = $this->driver;
        }
     
        if(method_exists($driver,$method))
        {
            return call_user_func_array(array($driver,$method), $params);
        }
    	else
        {
            throw new \Exception("the requested function (".$method.") is not available for the requested driver ");         
        }
    }

   protected function loadDatabaseDriver($authDriverName){

       
       $databaseDriverClass = 'T4s\CamelotAuth\Database\\'.ucfirst($authDriverName).'Database';
       return new $databaseDriverClass($this->config);
   }






    /**
    * Get the event dispatcher instance.
    *
    * @return T4s\CamelotAuth\Events\DispatcherInterface
    */
    public function getEventDispatcher()
    {
         if(!is_null($this->driver))
         {
            return $this->driver->getEventDispatcher();
         }   
        return $this->events;
    }

    /**
    * Set the event dispatcher instance.
    *
    * @param T4s\CamelotAuth\Events\DispatcherInterface
    */
    public function setEventDispatcher(DispatcherInterface $events)
    {
        $this->events = $events;
        if(!is_null($this->driver))
         {
            return $this->driver->setEventDispatcher($events);
         }  
    }

}