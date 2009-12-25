<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <title>Application (Themed)</title>
    <!--[if IE]>
    <script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
    <style>
      article, aside, dialog, figure, footer, header, hgroup, menu, nav, section { display: block; }
    </style>
    <!-- Created by Matthew McCray on 2009-12-24 -->
    <link rel="stylesheet" href="<?= theme_url_for('styles/screen.css') ?>" type="text/css" media="screen" title="no title" charset="utf-8"/>
  </head>
  <body>
    <?= $contentForLayout ?>
  </body>
</html>