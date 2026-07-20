 # TODO

## CSS/JS module path fixes
- [x] Scan all PHP pages for `<link href="...css">` and `<script src="...js">` that use relative paths.
- [x] Fix relative paths for pages located in subfolders (e.g. `pos/`, `aac/`, `orm/`, etc.) so they correctly point to root assets using `../` as needed. (Implemented for `pos/stock-monitoring.php`.)
- [ ] Fix any internal `include` / `require` paths if they are using relative paths instead of `__DIR__`.
- [ ] Ensure notification JS/CSS (`notif-bell.js`, etc.) are loaded from the correct location on all pages.
- [ ] Smoke test: open the affected pages and verify network requests for CSS/JS return 200.

## Runtime error fix
- [x] Fix broken includes on `pos/pos.php` (`db.php` / `promotion_engine.php`) so it loads from the correct root.
- [x] Fix `Undefined type 'Psr\Log\LoggerInterface'` — created a PSR-3 LoggerInterface stub at `Psr/Log/LoggerInterface.php` and added a PSR-4 autoloader in `db.php` so PHPMailer's `instanceof` checks resolve correctly without needing Composer.

