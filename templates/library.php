
<?php defined('ABSPATH') || exit;
if (!is_user_logged_in()) {
    echo '<p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to view your library.</p>';
    return;
}
?>
<div class="bs-wrap" data-bs-library>

  <div class="bs-tabs">
    <button class="bs-tab-btn js-tab-btn" data-tab="bs-my-books">My Books</button>
    <button class="bs-tab-btn js-tab-btn" data-tab="bs-incoming">Rental Requests</button>
  </div>

  <div id="bs-my-books" class="bs-tab-panel js-tab-panel">
    <h2 style="font-family:'Playfair Display',serif;margin-bottom:1rem">Your Library</h2>
    <div class="bs-grid js-library-grid"></div>
  </div>

  <div id="bs-incoming" class="bs-tab-panel js-tab-panel">
    <h2 style="font-family:'Playfair Display',serif;margin-bottom:1rem">Incoming Rental Requests</h2>
    <div class="js-incoming-grid"></div>
  </div>

</div>