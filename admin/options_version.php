<?php
if (!defined('ABSPATH')) die();

if (isset($detected['versions']))
{
  if (is_array($detected['versions']) && $c = count($detected['versions']))
  {
    if ($c > 1)
    {
      echo '<b>'.__('WARNING:', self::ld).'</b> ';
      _e('On the webpage was detected more than one version of Bootstrap:', self::ld);
      echo ' <b>'.implode(', ', $detected['versions']).'</b>';
    }
    else
    {
      _e('On the webpage was detected version of Bootstrap:', self::ld);
      echo ' <b>'.$detected['versions'][0].'</b>';
    }
  }
  else
    _e('On the webpage was not detected Bootstrap.', self::ld);
}
else
  _e('The version of Bootstrap used on the webpage was not yet checked. Please click on the button below.', self::ld);
?>
