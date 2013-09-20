jQuery(document).ready(function($)
{
  var $version_html = $('#bscdn_version');
  var $check_button = $('.bscdn-check-version-button');
  var $spinner = $('.bscdn-spinner');

  function setWait(state)
  {
    if (state)
    {
      $check_button.attr('disabled', true);
      $spinner.show();
    }
    else
    {
      $check_button.attr('disabled', false);
      $spinner.hide();
    }
  }

  // check actual version of bootstrap
  $check_button.on('click', function()
  {
    setWait(true);
    $.post(BootstrapCDN.ajax_admin, { a: 'detect' }, function(r)
    {
      setWait(false);

      if (r.status && r.status == 1)
      {
        $version_html.removeClass('bscdn-error-color').html(r.data);

        // select firstly detected version
        if (r.version)
        {
          var $s = $('#bscdn_bootstrap_version');
          var $o = $('option[value="' + r.version + '"]');
          if ($o.length > 0)
          {
            $('option:selected', $s).attr('selected', false);
            $o.attr('selected', true);
          }
        }

      }
      else
      if (r.message)
        $version_html.addClass('bscdn-error-color').html(r.message);
    }).error(function()
    {
      setWait(false);
      alert(BootstrapCDN.text.ajax_error);
    });
  });

  var $updated = $('.updated:not(.bscdn_update_notice)');
  if ($updated.length > 0)
    window.setTimeout(function()
    {
      $updated.fadeOut(600, function()
      {
        $updated.hide();
      });
    }, 4000);
});