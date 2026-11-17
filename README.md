# Server Clock

The dynamic site for COP 3350 Option 2. On each request, PHP reads the server clock and renders the date and time in US Eastern Time. The **Refresh time** button sends another GET request. Wait a few seconds between requests to demonstrate that PHP generates a new timestamp.

To preview with PHP installed, run `php -S 127.0.0.1:8000` from this directory and open `http://127.0.0.1:8000/`.

For the lab, copy `index.php` and `style.css` into the clock virtual host’s document root. Enable PHP handling and set `index.php` as the directory index. The form and stylesheet work with whichever hostname is assigned to the virtual host.
