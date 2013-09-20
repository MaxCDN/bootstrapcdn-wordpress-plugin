<?php
/*
Plugin Name: Bootstrap CDN
Plugin URI: http://www.bootstrapcdn.com
Description: Intregrate Bootstrap CDN into your WordPress
Author: MaxCDN
Version: 0.0.2
Author URI: http://www.maxcdn.com
Text Domain: bootstrap-cdn
License: GPLv2
*/

if (!defined('ABSPATH')) die();

class BootstrapCDNException extends Exception { }

class BootstrapCDN
{
  const ld = 'bootstrap-cdn';
  const version = '0.0.2';
  const nonce = 'bootstrap-cdn-nonce';

  const user_agent = 'Bootstrap CDN Plugin for Wordpress';
  const update_url = 'http://netdna.bootstrapcdn.com/data/bootstrapcdn.json';

  protected $_url, $_path;

  protected $enabled = false;

  public function __construct()
  {
    // paths
    $this->_url = plugins_url('', __FILE__);
    $this->_path = dirname(__FILE__);

    // load domain language on plugins_loaded action
    add_action('plugins_loaded', array($this, 'plugins_loaded'));

    $this->enabled = get_option(__class__.'_enabled', false);

    if (is_admin())
    {
      // add options page
      add_action('admin_menu', array($this, 'admin_menu'));

      // enqueue scripts and styles
      add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

      // handle admin ajax actions
      add_action('wp_ajax_'.__class__, array($this, 'ajax_admin'));

      // show notice when update is available
      if (!isset($_GET['cdn_update']) &&
          get_option(__class__.'_cdn_update_data', false) &&
          ((isset($_GET['page'])?$_GET['page'] == __class__:false) || !get_option(__class__.'_update_notice_dismissed', false) ))
      {
        add_action('admin_notices', array($this, 'update_notice'));
      }

      add_action('admin_init', array($this, 'options_init'));
    }
    else
    if ($this->enabled) // if plugin is enabled
      add_action('init', array($this, 'init_replace'), 0); // should have the highest priority

    // check for updates
    add_action(__class__.'_checkUpdate', array($this, 'check_update'));

    // on activation/uninstallation hooks
    register_activation_hook(__FILE__, array($this, 'activation'));
    register_deactivation_hook(__FILE__, array($this, 'deactivation'));
    register_uninstall_hook(__FILE__, array(__class__, 'uninstall'));
  }

  // load localization text domain
  public function plugins_loaded()
  {
    load_plugin_textdomain(self::ld, false, dirname(plugin_basename(__FILE__)).'/languages/');
  }

  // on activation
  public function activation()
  {
    // set initial data about available files on CDN
    if (!get_option(__class__.'_cdn_data', false))
    {
      $data = json_decode(file_get_contents($this->_path.'/bootstrapcdn.json'), ARRAY_A);
      add_option(__class__.'_cdn_data', $data);
    }

    // schedule updating of available files from CDN
    if (!wp_next_scheduled(__class__.'_checkUpdate'))
      wp_schedule_event(time(), 'daily', __class__.'_checkUpdate');

  }

  // on deactivation
  public function deactivation()
  {
    // remove schedule for updating
    wp_clear_scheduled_hook(__class__.'_checkUpdate');
  }

  // on uninstallation
  static function uninstall()
  {
    delete_option(__class__.'_cdn_data');
    delete_option(__class__.'_cdn_update_data');
    delete_option(__class__.'_update_notice_dismissed');
  }

  // add options page
  public function admin_menu()
  {
    add_options_page(__('Bootstrap CDN', self::ld), __('Bootstrap CDN', self::ld), 'manage_options', __class__, array($this, 'options_page'));
    add_filter('plugin_action_links_'.plugin_basename(__FILE__), array($this, 'filter_plugin_actions'), 10, 2);
  }

  // extend menu on the plugin listing page
  public function filter_plugin_actions($l, $file)
  {
    $settings_link = '<a href="options-general.php?page='.__class__.'">'.__('Settings').'</a>';
    array_unshift($l, $settings_link);
    return $l;
  }

  // enqueue scripts and styles in the admin
  public function admin_enqueue_scripts($hook)
  {
    // our options page
    if ($hook == 'settings_page_'.__class__)
    {
      wp_enqueue_style(__class__.'_styles', $this->_url.'/admin/options.css', array(), self::version, 'all');

      wp_enqueue_script('jquery');
      wp_enqueue_script(__class__, $this->_url.'/admin/options.js', array('jquery'), self::version);
      wp_localize_script(__class__, __class__, array(
        'ajax_admin' => admin_url('admin-ajax.php?action='.__class__),
        'text' => array(
          'ajax_error' => __('Error occurred during AJAX request. Please try again later.', self::ld)
        )
      ));
    }
  }


  // actions for options page
  public function options_init()
  {
    $page = isset($_GET['page'])?$_GET['page']:false;
    if ($page != __class__) return;

    $options_url = admin_url('options-general.php?page='.__class__);

    // save options
    if (isset($_POST['save_options']) && $_POST['save_options'])
    {
      if (!wp_verify_nonce($_POST['_wpnonce'], self::nonce))
        die(__('Whoops! There was a problem with the data you posted. Please go back and try again.', self::ld));


      update_option(__class__.'_enabled', isset($_POST['bscdn_status']) && $_POST['bscdn_status'] == 1?true:false);

      $options = array(
        'bootstrap_version' => isset($_POST['bscdn_bootstrap_version'])?$_POST['bscdn_bootstrap_version']:false,
        'bootstrap_not_js' => isset($_POST['bscdn_bootstrap_not_js'])?$_POST['bscdn_bootstrap_not_js']:false,
        'fontawesome_version' => isset($_POST['bscdn_fontawesome_version'])?$_POST['bscdn_fontawesome_version']:false
      );

      update_option(__class__.'_options', $options);

      $this->create_replace_data();

      $this->cache_flush();

      wp_redirect(add_query_arg(array('message' => 'saved'), $options_url));
      exit;
    }

    // update cdn data
    if (isset($_GET['update_cdn']))
    {
      if ($data = get_option(__class__.'_cdn_update_data', false))
      {
        update_option(__class__.'_cdn_update_data', false);
        update_option(__class__.'_cdn_data', $data);
      }
      else
        return;

      $this->create_replace_data();

      $this->cache_flush();

      wp_redirect(add_query_arg(array('message' => 'updated'), $options_url));
      exit;
    }
  }

  // options page
  public function options_page()
  {
    $options_url = admin_url('options-general.php?page='.__class__);
    $message = isset($_GET['message'])?$_GET['message']:false;

    if ($message == 'saved')
      echo '<div class="updated"><p>'.__('Settings were sucessfully saved.', self::ld).'</p></div>';

    if ($message == 'updated')
      echo '<div class="updated"><p>'.__('CDN data were updated.', self::ld).'</p></div>';

    $options = get_option(__class__.'_options', array());
    if (!is_array($options)) $options = array();

    $detected = get_option(__class__.'_detected', array());
    if (!is_array($detected)) $detected = array();

    $cdn_data = get_option(__class__.'_cdn_data', array());
    if (!is_array($cdn_data)) $cdn_data = array();

    // try use fallback if we are not have valid CDN data
    if (!$cdn_data || !is_array($cdn_data) || !count($cdn_data))
    {
      $cdn_data = json_decode(file_get_contents($this->_path.'/bootstrapcdn.json'), ARRAY_A);
      update_option(__class__.'_cdn_data', $cdn_data);
    }

    require_once $this->_path.'/admin/top.php';
    require_once $this->_path.'/admin/options.php';
  }

  // handle ajax actions
  public function ajax_admin()
  {
    header("Content-Type: application/json");
    if (!isset($_POST['a'])) exit();

    switch($_POST['a'])
    {
      // detect bootstrap version used in wordpress
      case 'detect':
        try
        {
          $detected = $this->detectBootstrap();
        }
        catch(BootstrapCDNException $e)
        {
          echo json_encode(array('status' => 2, 'message' => $e->getMessage()));
          exit;
        }

        ob_start();
        require_once $this->_path.'/admin/options_version.php';
        $c = ob_get_contents();
        ob_end_clean();

        if (is_array($detected) && isset($detected['versions']) && count($detected['versions']))
          $version = $detected['versions'][0];
        else
          $version = false;

        echo json_encode(array('status' => 1, 'data' => $c, 'version' => $version));
        break;

      // dismiss update notice
      case 'dismiss_update_notice':
        update_option(__class__.'_update_notice_dismissed', true);
        echo json_encode(array());
        break;
    }

    exit();
  }

  // update notice at top of admin area
  function update_notice()
  {
    require_once $this->_path.'/admin/update_notice.php';
  }

  // check update on CDN server
  function check_update()
  {
    $remote_data = $this->get_content(self::update_url);

    if (!$remote_data || !$remote_data = @json_decode($remote_data, ARRAY_A))
      return false;

    $current_data = get_option(__class__.'_cdn_data', false);

    // save available update
    if ($current_data->timestamp != $remote_data->timestamp)
    {
      update_option(__class__.'_update_notice_dismissed', false);
      update_option(__class__.'_cdn_update_data', $remote_data);
    }
  }


  // get valid version string
  protected function getValidVersion($str)
  {
    if (preg_match('/\d*\.\d*\.\d*/', $str, $o))
      return $o[0];

    return false;
  }

  // get version from bootstrap file
  protected function getBSVersion($url)
  {
    $data = $this->get_content($url);

    if ($data && $version = $this->getValidVersion($data))
      return $version;

    return false;
  }

  // detect bootstrap on webpage
  protected function detectBootstrap()
  {
    // disable this plugin
    update_option(__class__.'_enabled', false);

    // we also need to disable other plugins that can affect result
    $deactivate_plugins = array(
      'w3-total-cache.php', 'wp-cache.php'
    );

    $deactivate_match = '/'.@implode('|', array_map('preg_quote', $deactivate_plugins)).'/i';

    $active_plugins = get_option('active_plugins');
    $new_active_plugins = array();

    foreach($active_plugins as $active_plugin)
      if (!preg_match($deactivate_match, $active_plugin))
        $new_active_plugins[] = $active_plugin;

    update_option('active_plugins', $new_active_plugins);

    // pages content to check
    $pages = array();
    $pages[] = get_bloginfo('home').'/';

    $data = '';
    while(list(, $page) = @each($pages))
      if ($c = $this->get_content($page))
        $data.= $c;


    $detected = false;
    $versions = array();
    $css_list = false;
    $js_list = false;

    // looking for bootstrap
    if (preg_match_all('/(<link.[^>]*href\s*=\s*[\'"](.[^"\']*\/bootstrap.[^"\']+\.css)(\?ver=(.[^\'"]+?)|)[\'"].*?'.'>)|(<script.[^>]*src\s*=\s*[\'|"](.[^\?"\']*\/bootstrap.[^\?"\']+)(\?ver=(.[^\'"]+)|)[\'|"].*?<\/script>)/is', $data, $o))
    {

      $css_list = array();
      $js_list = array();
      $main_url = get_site_url().'/';

      while(list($k, $v) = @each($o[0]))
      {
        if ($isCSS = $o[2][$k]) // CSS file
        {
          $f = 2;
          $ver = 4;
        }
        else
        if ($isJS = $o[6][$k]) // JS file
        {
          $f = 6;
          $ver = 8;
        }

        $url = $o[$f][$k];
        $version = $o[$ver][$k]?$this->getValidVersion($o[$ver][$k]):$this->getBSVersion($url);

        if (!in_array($version, $versions))
          $versions[] = $version;

        if ($isCSS)
        {
          $css_list[] = array(
            'tag' => $v,
            'url' => $url.$o[3][$k]
          );
        }
        else
        if ($isJS)
        {
          $js_list[] = array(
            'tag' => $v,
            'url' => $url.$o[7][$k]
          );
        }
      }
    }

    // save result
    update_option(__class__.'_detected', $detected = array(
      'css_files' => $css_list,
      'js_files' => $js_list,
      'versions' => $versions
    ));

    // and again enable if was enabled before
    update_option(__class__.'_enabled', $this->enabled);

    // enable back deactivated plugins
    update_option('active_plugins', $active_plugins);

    $this->cache_flush();

    return $detected;
  }


  // generate replace data
  protected function create_replace_data()
  {
    $options = get_option(__class__.'_options', array());
    if (!is_array($options)) $options = array();

    $detected = get_option(__class__.'_detected', array());
    if (!is_array($detected)) $detected = array();

    $cdn_data = get_option(__class__.'_cdn_data', array());
    if (!is_array($cdn_data)) $cdn_data = array();

    $replace_from = array();
    $replace_to = array();

    // font awesome stuff
    if (isset($cdn_data['fontawesome']) && is_array($cdn_data['fontawesome']) &&
      isset($options['fontawesome_version']) && isset($cdn_data['fontawesome'][$options['fontawesome_version']]))
    {
      $fontawesome = $cdn_data['fontawesome'][$options['fontawesome_version']];
      $replace_from[] = '<head>';
      $replace_to[] = '<head>'.PHP_EOL.'<link rel="stylesheet" type="text/css" href="'.$fontawesome.'" />';
    }

    // bootstrap CSS
    if (isset($cdn_data['bootstrap']) && is_array($cdn_data['bootstrap']) &&
      isset($options['bootstrap_version']) && isset($cdn_data['bootstrap'][$options['bootstrap_version']]))
    {
      $bootstrap = $cdn_data['bootstrap'][$options['bootstrap_version']];

      if (isset($detected['css_files']) && is_array($detected['css_files']))
      {
        $first = true;
        foreach($detected['css_files'] as $file)
        {
          $replace_from[] = $first?$file['url']:$file['tag'];
          $replace_to[] = $first?$bootstrap['css']:'';
          $first = false;
        }
      }
      else
      {
        $replace_from[] = '<head>';
        $replace_to[] = '<head>'.PHP_EOL.'<link rel="stylesheet" type="text/css" href="'.$bootstrap['css'].'" />';
      }

      $not_js = isset($options['bootstrap_not_js']) && $options['bootstrap_not_js']?true:false;

      if (isset($detected['js_files']) && is_array($detected['js_files']))
      {
        $first = true;
        foreach($detected['js_files'] as $file)
        {
          $replace_from[] = $first && !$not_js?$file['url']:$file['tag'];
          $replace_to[] = $first && !$not_js?$bootstrap['js']:'';
          $first = false;
        }
      }
      else
      if (!$not_js)
      {
        $replace_from[] = '</head>';
        $replace_to[] = '<script type="text/javascript" src="'.$bootstrap['js'].'"></script>'.PHP_EOL.'</head>';
      }
    }

    update_option(__class__.'_replace_from', $replace_from);
    update_option(__class__.'_replace_to', $replace_to);
  }


  // output buffer callback
  public function callback_replace($buffer)
  {
    $f = fopen(ABSPATH.'/wp-content/test.txt', 'w');
    fwrite($f, $buffer);
    fclose($f);

    $replace_from = get_option(__class__.'_replace_from', false);
    $replace_to = get_option(__class__.'_replace_to', false);

    if ($replace_from && $replace_to)
      return str_replace($replace_from, $replace_to, $buffer);
    else
      return $buffer;
  }

  // init replace callback
  public function init_replace()
  {
    ob_start(array($this, 'callback_replace'));
  }

  // flush cache for known cache plugins
  protected function cache_flush()
  {
    // W3 Total Cache
    if (function_exists('w3tc_pgcache_flush'))
      w3tc_pgcache_flush();

    if (function_exists('w3tc_dbcache_flush'))
      w3tc_dbcache_flush();

    if (function_exists('w3tc_minify_flush'))
      w3tc_minify_flush();

    if (function_exists('w3tc_objectcache_flush'))
      w3tc_objectcache_flush();

    // WP Super Cache
    if (function_exists('wp_cache_clear_cache'))
      wp_cache_clear_cache();

  }

  // load content of website at URL address
  protected function get_content($url)
  {
    $handled = false;
    $data = false;

    if (function_exists('curl_init')) // CURL method
    {
      $ch = curl_init();
      curl_setopt_array($ch,
              array(CURLOPT_URL => $url,
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_USERAGENT => self::user_agent,
                    CURLOPT_HEADER => true,
                    CURLOPT_RETURNTRANSFER => true
              ));

      $data = curl_exec($ch);
      $delimiter = strpos($data, "\r\n\r\n");
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      // handle redirect
      if ($http_code == 301 || $http_code == 302)
      {
        $header = substr($data, 0, $delimiter);
        $matches = array();
        preg_match('/Location:(.*?)\n/', $header, $matches);
        $url = @parse_url(trim(array_pop($matches)));
        if ($url)
        {
          $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
          if (!$url['scheme']) $url['scheme'] = $last_url['scheme'];
          if (!$url['host']) $url['host'] = $last_url['host'];
          if (!$url['path']) $url['path'] = $last_url['path'];
          $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . (isset($url['query']) && $url['query']?'?'.$url['query']:'');
          $data = $this->get_content($new_url);
        }
      }
      else
        $data = substr($data, $delimiter + 4, strlen($data) - $delimiter - 4);

      $handled = true;
      curl_close($ch);
    }
    else
    {
      $wrappers = stream_get_wrappers();

      // uses fopen http wrapper via function file_get_contents
      if (ini_get('allow_url_fopen') && in_array('http', $wrappers))
      {
        $data = file_get_contents($url);

        if ($data === false)
          throw new BootstrapCDNException(__('Cannot get content from remote server via function file_get_contents. Please check configuration of your server or install CURL.', self::ld));

        $handled = true;
      }
      else // still we can try fsockopen
      {
        // get supported transports
        $transports = stream_get_transports();
        if (in_array('tcp', $transports) && in_array('ssl', $transports))
        {
          $url = parse_url($url);
          $ssl = stripos($url['scheme'], 'https') !== false;

          $f = fsockopen(($ssl?'ssl':'tcp').'://'.$url['host'], $ssl?443:80, $errno, $errstr);
          if ($f)
          {
            $s = "GET ".$url['path']." HTTP/1.1\r\n";
            $s.= "Host: ".$url['host']."\r\n";
            $s.= "User-Agent: ".self::user_agent."\r\n";
            $s.= "Connection: Close\r\n\r\n";
            fwrite($f, $s);

            $output = '';
            while(!feof($f) && $line = fgets($f))
              $output.= $line;

            fclose($f);

            // check if it's redirect
            if (preg_match('/HTTP\/.{3} (301|302)/', $output))
            {
              // get location
              if (preg_match('/Location: (.*?)\r/i', $output, $o))
                $data = $this->get_content($o[1]);
            }
            else
            {
              $delimiter = strpos($output, "\r\n\r\n");
              $data = substr($output, $delimiter + 4, strlen($output) - $delimiter - 4);
            }

            $handled = true;
          }
        }
      }
    }

    if (!$handled)
      throw new BootstrapCDNException(__('Cannot get content from remote server. Please enable CURL or allow_url_fopen with appropriate wrappers or tcp/ssl transports on your server.', self::ld));

    return $data;
  }

  // helper strip function
  static function strip($t)
  {
    return htmlentities($t, ENT_COMPAT, 'UTF-8');
  }

}

new BootstrapCDN();