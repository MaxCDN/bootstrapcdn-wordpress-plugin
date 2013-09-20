<?php
if (!defined('ABSPATH')) die();

$page_name = 'BootstrapCDN';
$plugin_page = (isset($_GET['page'])?$_GET['page'] == $page_name:false);
?>
<div class="updated bscdn_update_notice">
  <p>
    <?php if (!$plugin_page) { ?><b><?php _e('Bootstrap CDN:', self::ld); ?></b><?php } ?>
    <?php _e('New update of the CDN data available, please', self::ld); ?>
    <a class="button" style="margin-left: 5px; margin-right: 5px;" href="<?php echo admin_url('options-general.php?page='.$page_name.'&update_cdn=1'); ?>" title="<?php _e('Update CDN data', self::ld); ?>"><?php _e('update CDN data', self::ld); ?></a>
    .
    <?php
    if (!$plugin_page)
    {
    ?>
      <a class="button bscdn_dismiss_update_notice" style="margin-left: 10px; margin-right: 5px; float: right;" href="#" onclick="return false;" title="<?php _e('Dismiss', self::ld); ?>"><?php _e('Dismiss', self::ld); ?></a>
      <script>
      jQuery(document).ready(function($)
      {
        $('.bscdn_dismiss_update_notice').bind('click', function()
        {
          $.post('<?php echo admin_url('admin-ajax.php?action='.$page_name); ?>', {'a': 'dismiss_update_notice'}, function(r)
          {
            $('.bscdn_update_notice').fadeOut(400);
          });
        });
      });
      </script>
    <?php
    }
    ?>
    <br class="clear" />
  </p>
</div>
