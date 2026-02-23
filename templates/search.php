<?php defined('ABSPATH') || exit; ?>
<div class="bs-wrap" data-bs-search>
  <div class="bs-code-search">
    <h2>🔍 Find a Book by Unique Code</h2>
    <p style="color:var(--bs-muted);margin-bottom:1rem;">Enter a book's unique code to see which community members have it in their public library.</p>
    <div class="bs-code-row">
      <input class="bs-input js-code-input" type="text" placeholder="e.g. DUNE3F9A" style="text-transform:uppercase">
      <button class="bs-btn bs-btn--primary js-code-search-btn">Search</button>
    </div>
  </div>
  <div class="bs-grid js-code-results"></div>
</div>