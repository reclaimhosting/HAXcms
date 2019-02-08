<?php
  include_once '../system/lib/bootstrapHAX.php';
  include_once $HAXCMS->configDirectory . '/config.php';
  // test if this is a valid user login
  if ($HAXCMS->validateJWT()) {
    header('Content-Type: application/json');
    // load the site from name
    $site = $HAXCMS->loadSite($HAXCMS->safePost['siteName']);
    $original = $site->manifest->items;
    $items = $_POST['items'];
    $itemMap = array();
    // items from the POST
    foreach ($items as $key => $item) {
      // get a fake item
      if (!$page = $site->loadPage($item->id)) {
        $page = $HAXCMS->outlineSchema->newItem();
      }
      else {
        $page->id = $item->id;
      }
      // set a crappy default title
      $page->title = $item->title;
      if ($item->parent == NULL) {
        $page->parent = NULL;
        $page->indent = 0;
      }
      else {
        // set to the parent id
        $page->parent = $item->parent;
        // move it one indentation below the parent; this can be changed later if desired
        $page->indent = $item->indent;
      }
      if (isset($item->order)) {
        $page->order = $item->order;
      }
      else {
        $page->order = $key;
      }
      // keep location if we get one already
      if (isset($item->location) && $item->location != '') {
        // force location to be in the right place
        $cleanTitle = str_replace('pages/', '', str_replace('/index.html', '', $item->location));
        $cleanTitle = strtolower(str_replace(' ', '-', $cleanTitle));
        $cleanTitle = preg_replace('/[^\w\-\/]+/u', '-', $cleanTitle);
        $cleanTitle = mb_strtolower(preg_replace('/--+/u', '-', $cleanTitle), 'UTF-8');
        $page->location = 'pages/' . $cleanTitle . '/index.html';
      }
      else {
        $cleanTitle = strtolower(str_replace(' ', '-', $page->title));
        $cleanTitle = preg_replace('/[^\w\-\/]+/u', '-', $cleanTitle);
        $cleanTitle = mb_strtolower(preg_replace('/--+/u', '-', $cleanTitle), 'UTF-8');
        // generate a logical page location
        $page->location = 'pages/' . $cleanTitle . '/index.html';
      }
      // verify this exists, front end could have set what they wanted
      // or it could have just been renamed
      $siteDirectory = $site->directory . '/' . $site->manifest->metadata->siteName;
      if (!file_exists($siteDirectory . '/' . $page->location)) {
        $moved = false;
        foreach ($original as $key => $tmpItem) {
          // see if this is something moving as opposed to brand new
          if ($tmpItem->id == $page->id && file_exists($siteDirectory . '/' . $tmpItem->location) && $tmpItem->location != '') {
            $moved = true;
            rename(str_replace('/index.html', '', $siteDirectory . '/' . $tmpItem->location), 
            str_replace('/index.html', '', $siteDirectory . '/' . $page->location));
          }
        }
        if (!$moved) {
          $site->recurseCopy(HAXCMS_ROOT . '/system/boilerplate/page', $siteDirectory . '/pages/' . $cleanTitle);
          // if we got here ensure that location data matches
          // this accounts for pages that got deleted at one time physically off the file system
          $page->location = 'pages/' . $cleanTitle . '/index.html';
        }
      }
      // if it doesn't exist currently make sure the name is unique
      else if (!$site->loadPage($page->id)) {
        // ensure this location doesn't exist already
        $loop = 0;
        while (file_exists($siteDirectory . '/' . $page->location)) {
          $loop++;
          $page->location = 'pages/' . $cleanTitle . '-' . $loop . '/index.html';
        }
      }
      // check for any metadata keys that did come over
      foreach ($item->metadata as $key => $value) {
        $page->metadata->{$key} = $value;
      }
      // safety check for new things
      if (!isset($page->metadata->created)) {
        $page->metadata->created = time();
      }
      // always update at this time
      $page->metadata->updated = time();
      if ($site->loadPage($page->id)) {
        $site->updatePage($page);
      }
      else {
        $site->manifest->addItem($page);
      }
    }
    $site->manifest->metadata->updated = time();
    $site->manifest->save();
    $site->gitCommit('Outline updated in bulk');
    header('Status: 200');
    print json_encode($site->manifest->items);
    exit;
  }
?>