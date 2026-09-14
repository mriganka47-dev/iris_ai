# Iris — setup & deployment

## Files in this project

| File | Committed to GitHub? | What it does |
|---|---|---|
| `index.html` | ✅ yes | The whole app — UI, chat logic, calls Gemini directly from the browser |
| `config.php` | ✅ yes | Reads `.env` server-side, hands the Gemini keys + Google Client ID to `index.html` |
| `exa-proxy.php` | ✅ yes | Reads `.env` server-side, calls Exa's search API on the server (Exa's API blocks direct browser calls) |
| `env.php` | ✅ yes | Tiny shared helper both PHP files use to read `.env` |
| `.env.example` | ✅ yes | Template showing which variables are needed — has no real values in it |
| `.gitignore` | ✅ yes | Tells git to never commit `.env` |
| `.env` | ❌ **never** | Your **real** API keys. Excluded by `.gitignore` on purpose |

## Does Iris need `.env` to be uploaded somewhere, or not?

Both — just not in the same place. There are two separate systems here and it's easy to mix them up:

- **GitHub** = your source code history, public if your repo is public. `.env` must never go here — that's what `.gitignore` enforces.
- **InfinityFree (your live server)** = where the app actually *runs*. It genuinely needs your real keys to work, so `.env` **does** need to exist there — you just don't get it there through git. You upload it directly (FTP or InfinityFree's File Manager), as a completely separate step from pushing code to GitHub.

So: push everything to GitHub *except* `.env` (git already won't let you, since it's ignored) → then separately upload `.env` straight to the server, alongside the rest of the files.

## One important security nuance

Keeping `.env` off GitHub protects your keys from anyone browsing your **source code**. It does **not** hide everything from anyone visiting your **live site** — and that split matters here:

- **Exa keys**: fully hidden, always. They're only ever read inside `exa-proxy.php`, which runs on your server. The browser never receives them, so there's nothing to find even in the Network tab.
- **Gemini key + Google Client ID**: hidden from GitHub, but **visible to anyone who opens DevTools on your live site** (Network tab, or view-source after `config.php` runs). This is because `index.html` calls Gemini *directly from the browser* — the browser needs the real key to do that, so it has to receive it somehow. `.env` keeps it out of your repo; it can't keep it out of a page that hands it to the browser at runtime.

If that's fine for your use (your own personal key, low traffic, you're not worried about someone scraping it off your live page) — no action needed. If you want the Gemini key fully hidden the same way Exa's is, the fix is the same pattern: route Gemini calls through a PHP proxy too, instead of calling it directly from `index.html`. That's a bigger change (Gemini's responses stream token-by-token, which is trickier through a proxy on shared hosting) — say the word and it can be built.

## Local testing

Opening `index.html` directly by double-clicking it (`file://...`) **will not work** for Gemini/Exa/sign-in anymore, because `config.php` and `exa-proxy.php` need an actual PHP server to run. To test locally:

1. Copy `.env.example` to `.env` and fill in your real keys.
2. From this folder, run:
   ```
   php -S localhost:8000
   ```
3. Open `http://localhost:8000` in your browser (not the file path).

If you don't have PHP installed locally, skip local testing and just deploy to InfinityFree to test — see below.

## Deploying to InfinityFree

1. Push `index.html`, `config.php`, `exa-proxy.php`, `env.php`, `.env.example`, `.gitignore`, and this `README.md` to GitHub.
2. On InfinityFree, upload those same files into your site's `htdocs` folder (via their File Manager, or FTP/FileZilla — either works, this doesn't have to go through git at all).
3. **Separately**, create `.env` directly in that same `htdocs` folder (upload it via File Manager/FTP, or create it there and paste your keys in) with your real values:
   ```
   GEMINI_KEY_1=your_real_key
   GEMINI_KEY_2=your_real_key
   EXA_KEY_1=your_real_key
   EXA_KEY_2=your_real_key
   GOOGLE_CLIENT_ID=your_real_client_id.apps.googleusercontent.com
   ```
4. In Google Cloud Console, add your real InfinityFree domain (e.g. `https://yoursite.infinityfreeapp.com`) under **Authorized JavaScript origins** for that Client ID, or the real sign-in button won't render (it'll fall back to "Continue as Guest" instead, which always works regardless).
5. Visit your live site. If something's not working, visit `yoursite.com/config.php` and `yoursite.com/exa-proxy.php` directly in a browser — `config.php` should show a line like `window.IRIS_CONFIG = {...};` and `exa-proxy.php` should show `{"error":"Use POST"}`. Either of those confirms PHP is running correctly; a blank page or 500 error means something's wrong with the PHP setup itself rather than the app.
