<?php if (!defined('ABSPATH')) die(); ?>

<form action="<?php echo $options_url; ?>" method="post">
  <?php wp_nonce_field(self::nonce); ?>

  <h3>Current version</h3>
  <div id="bscdn_version">
  <?php require_once $this->_path.'/admin/options_version.php'; ?>
  </div>
  <p>
    <input type="button" name="check_version" class="button bscdn-check-version-button" value="<?php _e('Check version', self::ld); ?>" />
    <span class="spinner bscdn-spinner"></span>
    <br class="clear" />
  </p>


  <br />
  <h3>Insert / Replace Bootstrap</h3>
  <table class="form-table">
    <tr>
      <th scope="row"><label for="bscdn_status"><?php _e('Bootstrap CDN', self::ld); ?></label></th>
      <td>
        <select name="bscdn_status" id="bscdn_status">
          <option value="0"<?php echo (!$this->enabled?' selected':''); ?>><?php _e('Disabled', self::ld); ?></option>
          <option value="1"<?php echo ($this->enabled?' selected':''); ?>><?php _e('Enabled', self::ld); ?></option>
        </select>
      </td>
    </tr>
    <tr>
      <th scope="row"><label for="bscdn_bootstrap_version"><?php _e('Bootstrap version', self::ld); ?></label></th>
      <td>
        <select name="bscdn_bootstrap_version" id="bscdn_bootstrap_version">
        <?php
          if (isset($cdn_data['bootstrap']) && is_array($cdn_data['bootstrap']))
          {
            $selected_version = false;

            // check if version is set and if is valid, if not then try select detected version
            if (!isset($options['bootstrap_version']) || !isset($cdn_data['bootstrap'][$options['bootstrap_version']]))
            {
              if (isset($detected['versions']) && count($detected['versions']) && isset($cdn_data['bootstrap'][$detected['versions'][0]]))
                $selected_version = $detected['versions'][0];
            }
            else
              $selected_version = $options['bootstrap_version'];

            reset($cdn_data['bootstrap']);
            while(list($version, ) = @each($cdn_data['bootstrap']))
            {
              if (!$selected_version)
                $selected_version = $version;

              $v = explode('-', $version);
              $version_name = $v[0];

              $type_text = '';
              if (isset($v[1]))
              {
                if ($v[1] == 'noicons')
                  $type_text = __('No icons', self::ld);
                else
                if ($v[1] == 'noresponsible')
                  $type_text = __('No responsible', self::ld);
              }

              echo '<option value="'.$version.'"'.($selected_version == $version?' selected':'').'>'.$version_name.($type_text?' ('.$type_text.')':'').'</option>';
            }
          }
          else
            echo '<option value="0">'.__('No versions available', self::ld).'</option>';
        ?>
        </select>
        <label for="bscdn_bootstrap_not_js" class="bscdn-checkbox">
          <input type="checkbox" name="bscdn_bootstrap_not_js" id="bscdn_bootstrap_not_js"<?php echo (isset($options['bootstrap_not_js']) && $options['bootstrap_not_js']?' checked':''); ?> value="1" />
          <?php _e('Do not include Bootstrap Javascript file.', self::ld); ?>
        </lable>
      </td>
    </tr>

    <tr>
      <th scope="row"><label for="bscdn_fontawesome_version"><?php _e('Font Awesome version', self::ld); ?></label></th>
      <td>
        <select name="bscdn_fontawesome_version" id="bscdn_fontawesome_version">
          <option value=""><?php _e("Do not include", self::ld); ?></option>
        <?php
          if (isset($cdn_data['fontawesome']) && is_array($cdn_data['fontawesome']))
          {
            $selected_version = isset($options['fontawesome_version'])?$options['fontawesome_version']:false;

            reset($cdn_data['fontawesome']);
            while(list($version, ) = @each($cdn_data['fontawesome']))
              echo '<option value="'.$version.'"'.($selected_version == $version?' selected':'').'>'.$version.'</option>';
          }
        ?>
        </select>
      </td>
    </tr>
  </table>

  <?php submit_button(__('Save Changes', self::ld), 'primary', 'save_options', true); ?>
</form>