<?php


if (!function_exists('curl_init')) {
  throw new Exception('Gas needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
  throw new Exception('Gas needs the JSON PHP extension.');
}

class Gas
{
  /**
   * Version.
   */
  const VERSION = '1.0';

  /**
   * Signed Request Algorithm.
   */
  const SIGNED_REQUEST_ALGORITHM = 'HMAC-SHA256';

  /**
   * Default options for curl.
   */
  public static $CURL_OPTS = array(
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_USERAGENT      => 'gas-majestic-1',
  );


  /**
   * Maps aliases to Google Measurement Protocol domains.
   */
  public static $DOMAIN_MAP = array(
    'api'         => 'http://www.google-analytics.com/collect',
    'api_ssl'   => 'https://ssl.google-analytics.com/collect',
  );

  /**
   * The protocol version. The value should be 1.
   *
   * @var string
   */
  protected $protocolVersion;

  /**
   * The ID that distinguishes to which Google Analytics property to send data.
   *
   * @var string
   */
  protected $trackingID;

  /**
   * An ID unique to a particular visitor / user.
   *
   * @var string
   */
  protected $clientId;

  /**
   * The The type of interaction collected for a particular user.
   */
  protected $hitType;
  protected $source;

  /**
   * A CSRF state variable to assist in the defense against CSRF attacks.
   */
  protected $state;


  /**
   * Initialize a Gas Instance.
   *
   * The configuration:
   * - trackingId: the application ID
   * - clientId: the current user ID
   * - domainApp: the current domain
   *
   * @param array $config The instance configuration
   */
  public function __construct($config) {
    $this->setTrackingId($config['trackingId']);
    $this->setClientId($config['clientId']);
    $this->setDomainApp($config['domainApp']);
    $this->setUseSSL($config['useSSL']);
    $this->protocolVersion='1';
    $this->source=!empty($config['source'])? $config['source'] :'direct';

  }

  /**
   * Set the Tracking ID.
   *
   * @param string $trackingId The Google tracking ID
   */
  public function setTrackingId($trackingId) {
    $this->trackingId = $trackingId;
    return $this;
  }

  /**
   * Get the Application ID.
   *
   * @return string the Application ID
   */
  public function getTrackingId() {
    return $this->trackingId;
  }



  /**
   * Set the Client ID. this can be related to user. but it cant be a value that can be used to identify as individual. (email, DNI)
   *
   * @param string $clientId The App Secret
   */
  public function setClientId($clientId) {
    $this->clientId = $clientId;
    return $this;
  }


  /**
   * Get the Client Id.
   *
   * @return string the Current setted Client Id
   */
  public function getClientId() {
    return $this->clientId;
  }

   /**
   * Set the Protocol Version.
   *
   * @param string $protocolVersion The Version
   */
  public function setProtocolVersion($protocolVersion) {
    $this->protocolVersion = $protocolVersion;
    return $this;
  }


  /**
   * Get the Protocol Version.
   *
   * @return string the Current setted Client Id
   */
  public function getProtocolVersion() {
    return $this->protocolVersion;
  }


   /**
   * Set the Protocol Version.
   *
   * @param string $domainApp The Running domain you wanted to track
   */
  public function setDomainApp($domainApp) {
    $this->domainApp = $domainApp;
    return $this;
  }


  /**
   * Get the Domain App.
   *
   * @return string the Current setted Domain App
   */
  public function getDomainApp() {
    return $this->domainApp;
  }




   /**
   * Set SSL option.
   *
   * @param string $useSSL 1 if wanted to make calls over SSL
   */
  public function setUseSSL($useSSL) {
    $this->useSSL = $useSSL;
    return $this;
  }


  /**
   * Get the Use SSL.
   *
   * @return string the Current setted SSL option
   */
  public function getUseSSL() {
    return $this->useSSL;
  }

  /**
   * Get the API URL.
   *
   * @return string the Current setted SSL option
   */
  public function getAPIURL() {
    if ($this->useSSL)
      return self::$DOMAIN_MAP['api_ssl'];

    return self::$DOMAIN_MAP['api'];

  }



  public function buildRequiredParams(){

     return array('v'=>$this->protocolVersion, 'tid'=>$this->trackingId, 'cid'=>$this->clientId, 'z'=>time(), 'cs'=>$this->source);

  }

  /*
    v=1             // Version.
    &tid=UA-XXXX-Y  // Tracking ID / Web property / Property ID.
    &cid=555        // Anonymous Client ID.

    &t=pageview     // Pageview hit type.
    &dh=mydemo.com  // Document hostname.
    &dp=/home       // Page.
    &dt=homepage    // Title.
  */


  public function trackPageView($host=false, $page="/", $title=false) {

    if(!$host) $host=$this->domainApp;
    if(!$title) $title=$page;


    $params=$this->buildRequiredParams();

    $params['t']='pageview';
    $params['dh']=urlencode($host);
    $params['dp']=urlencode($page);
    $params['dt']=urlencode($title);

    return $this->makeRequest($this->getAPIURL(), $params);

  }


/*
  v=1             // Version.
  &tid=UA-XXXX-Y  // Tracking ID / Web property / Property ID.
  &cid=555        // Anonymous Client ID.

  &t=event        // Event hit type
  &ec=video       // Event Category. Required.
  &ea=play        // Event Action. Required.
  &el=holiday     // Event label.
  &ev=300         // Event value.
*/

  public function trackEvent($category, $action, $label="", $value="") {

    $params=$this->buildRequiredParams();

    $params['t']='event';
    $params['ec']=urlencode($category);
    $params['ea']=urlencode($action);
    $params['el']=urlencode($label);
    $params['ev']=urlencode($value);

    return $this->makeRequest($this->getAPIURL(), $params);

  }

/*
  v=1              // Version.
  &tid=UA-XXXX-Y   // Tracking ID / Web property / Property ID.
  &cid=555         // Anonymous Client ID.

  &t=transaction   // Transaction hit type.
  &ti=12345        // transaction ID. Required.
  &ta=westernWear  // Transaction affiliation.
  &tr=50.00        // Transaction revenue.
  &ts=32.00        // Transaction shipping.
  &tt=12.00        // Transaction tax.
  &cu=EUR          // Currency code.
*/

  public function trackTransaction($transactionId, $affiliation="", $revenue="", $shipping="", $tax="", $currency="") {

    $params=$this->buildRequiredParams();

    $params['t']='transaction';
    $params['ti']=urlencode($transactionId);
    $params['ta']=urlencode($affiliation);
    $params['tr']=urlencode($revenue);
    $params['ts']=urlencode($shipping);
    $params['tt']=urlencode($tax);
    $params['cu']=urlencode($currency);

    return $this->makeRequest($this->getAPIURL(), $params);

  }



/*
  v=1              // Version.
  &tid=UA-XXXX-Y   // Tracking ID / Web property / Property ID.
  &cid=555         // Anonymous Client ID.

  &t=item          // Item hit type.
  &ti=12345        // Transaction ID. Required.
  &in=sofa         // Item name. Required.
  &ip=300          // Item price.
  &iq=2            // Item quantity.
  &ic=u3eqds43     // Item code / SKU.
  &iv=furniture    // Item variation / category.
  &cu=EUR          // Currency code.
*/

  public function trackItem($transactionId, $name, $price="", $quantity="", $code="", $variation="", $currency="") {

    $params=$this->buildRequiredParams();

    $params['t']='item';
    $params['ti']=urlencode($transactionId);
    $params['in']=urlencode($item);
    $params['ip']=urlencode($price);
    $params['iq']=urlencode($quantity);
    $params['ic']=urlencode($code);
    $params['iv']=urlencode($variation);
    $params['cu']=urlencode($currency);

    return $this->makeRequest($this->getAPIURL(), $params);

  }


/*
  v=1             // Version.
  &tid=UA-XXXX-Y  // Tracking ID / Web property / Property ID.
  &cid=555        // Anonymous Client ID.

  &t=social       // Social hit type.
  &sa=like        // Social Action. Required.
  &sn=facebook    // Social Network. Required.
  &st=/home       // Social Target. Required.
*/

  public function trackSocialInteraction($action, $network, $target) {

    $params=$this->buildRequiredParams();

    $params['t']='social';
    $params['sa']=urlencode($action);
    $params['sn']=urlencode($network);
    $params['st']=urlencode($target);

    return $this->makeRequest($this->getAPIURL(), $params);

  }


/*
  v=1             // Version.
  &tid=UA-XXXX-Y  // Tracking ID / Web property / Property ID.
  &cid=555        // Anonymous Client ID.

  &t=exception      // Exception hit type.
  &exd=IOException  // Exception description.
  &exf=1            // Exception is fatal?
*/

  public function trackException($description="", $fatal=false) {

    $params=$this->buildRequiredParams();

    $params['t']='exception';
    $params['exd']=urlencode($description);
    $params['exf']=urlencode($fatal);

    return $this->makeRequest($this->getAPIURL(), $params);

  }


/*
  v=1             // Version.
  &tid=UA-XXXX-Y  // Tracking ID / Web property / Property ID.
  &cid=555        // Anonymous Client ID.

  &t=timing       // Timing hit type.
  &utc=jsonLoader // Timing category.
  &utv=load       // Timing variable.
  &utt=5000       // Timing time.
  &utl=jQuery     // Timing label.

  // These values are part of browser load times

  &dns=100        // DNS load time.
  &pdt=20         // Page download time.
  &rrt=32         // Redirect time.
  &tcp=56         // TCP connect time.
  &srt=12         // Server response time.
*/

  public function trackTiming($category="", $variable="", $time="",$label="",$dns="",$downtime="",$redirect="",$tcp="",$serverresponse=""  ) {

    $params=$this->buildRequiredParams();

    $params['t']='timing';
    $params['utc']=urlencode($category);
    $params['utv']=urlencode($variable);
    $params['utt']=urlencode($time);
    $params['utl']=urlencode($label);

    $params['dns']=urlencode($dns);
    $params['pdt']=urlencode($downtime);
    $params['rrt']=urlencode($redirect);
    $params['tcp']=urlencode($tcp);
    $params['srt']=urlencode($serverresponse);

    return $this->makeRequest($this->getAPIURL(), $params);

  }


/*
  v=1             // Version.
  &tid=UA-XXXX-Y  // Tracking ID / Web property / Property ID.
  &cid=555        // Anonymous Client ID.

  &t=appview      // Appview hit type.
  &an=funTimes    // App name.
  &av=4.2.0       // App version.

  &cd=Home        // Screen name / content description.
*/

  public function trackMobile($appname, $appversion, $screen) {

    $params=$this->buildRequiredParams();

    $params['t']='appview';
    $params['an']=urlencode($appname);
    $params['av']=urlencode($appversion);
    $params['cd']=urlencode($screen);

    return $this->makeRequest($this->getAPIURL(), $params);

  }




/*
  v=1             // Version.
  &tid=UA-XXXX-Y  // Tracking ID / Web property / Property ID.
  &cid=555        // Anonymous Client ID.

  &t=appview      // Appview hit type.
  &an=funTimes    // App name.
  &av=4.2.0       // App version.

  &cd=Home        // Screen name / content description.
*/

  public function trackMobileEvent($appname, $category, $action) {

    $params=$this->buildRequiredParams();

    $params['t']='event';
    $params['an']=urlencode($appname);
    $params['ec']=urlencode($category);
    $params['ea']=urlencode($action);

    return $this->makeRequest($this->getAPIURL(), $params);

  }



  protected function makeRequest($url, $params, $ch=null) {
    if (!$ch) {
      $ch = curl_init();
    }

    $opts = self::$CURL_OPTS;

    $opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');

    $opts[CURLOPT_URL] = $url;



    curl_setopt_array($ch, $opts);
    $result = curl_exec($ch);

    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);


    if ($result === false) {


      throw new Exception('Error, something is wrong.');


      curl_close($ch);
      throw $e;
    }
    curl_close($ch);
    return $http_status;
  }





}
