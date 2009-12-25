
You must login to access that feature.

<? if(@ $errorMsg): ?>
<div class="error"><?= $errorMsg ?></div>
<? endif ?>

<form action="<?= url_for('session/login')?>" method="POST">
  <input type="hidden" name="redirectTo" value="<?= $redirectTo ?>"/>
<p>
  <label for="username_field">Username:</label>
  <input id="username_field" type="text" name="username" />
</p>
<p>
  <label for="password_field">Password:</label>
  <input type="password" name="password" id="password_field" />
</p>
<p>
  <input type="submit" value="Login">
</p>
</form>

<script>
  document.getElementById('username_field').focus()
</script>
