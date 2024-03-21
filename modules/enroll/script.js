function initEnroll(text, country) {
  birthdayChanged('register_birthday', text);
  initProvince('register', country);
  var doLevelChanged = function() {
    send(WEB_URL + "index.php/enroll/model/plan/toJSON", 'level=' + this.value, function(xhr) {
      var ds = xhr.responseText.toJSON();
      if (ds) {
        forEach($E('setup_frm').getElementsByTagName('select'), function() {
          if (/register_plan\[[0-9]\]/.test(this.name)) {
            $G(this).parentNode.parentNode.style.display = ds.length == 0 ? 'none' : null;
            $G(this).setOptions(ds, this.value);
          }
        });
      }
    }, this)
  };
  $G('register_level').addEvent('change', doLevelChanged);
  doLevelChanged.call($E('register_level'));
}

function initEnrollPlan() {
  $G('level').addEvent('change', function() {
    loader.location('index.php?module=enroll-plan&level=' + this.value);
  });
}

function initEnrollSettings() {
  callClick('enroll_reset', function() {
    if (confirm(trans('YOU_WANT_TO_XXX').replace('XXX', this.innerHTML))) {
      send(WEB_URL + "index.php/enroll/model/setup/action", 'action=reset', doFormSubmit, this);
    }
  });
}
