<h1>Database Created</h1>
<p>Tables for the following models were created:</p>
<ul>
<? foreach($models as $mdl): ?>
  <li><?= $mdl ?></li>
<? endforeach; ?>
</ul>
<p>In addition, the initial Administration user was setup using the defaults in the application config file.</p>
<?= link_to('home', 'Home') ?>