<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#24206B">
        <title>Offline | {{ config('kasi.brand.name') }}</title>
        <style>
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f5f6fa; color: #16152b; }
            main { max-width: 22rem; padding: 2rem; text-align: center; }
            .mark { display: inline-grid; place-items: center; width: 3rem; height: 3rem; border-radius: .75rem; background: #f5b700; color: #24206b; font-weight: 700; font-size: 1.5rem; }
            h1 { font-size: 1.25rem; margin: 1rem 0 .5rem; }
            p { color: #5b5f77; line-height: 1.5; }
            button { margin-top: 1rem; min-height: 2.75rem; padding: 0 1.25rem; border: 0; border-radius: .625rem; background: #3b34b5; color: #fff; font-weight: 600; font-size: 1rem; }
        </style>
    </head>
    <body>
        <main>
            <span class="mark" aria-hidden="true">K</span>
            <h1>You're offline</h1>
            <p>There's no internet connection right now. Check your data or Wi-Fi, then try again. Your hub can help if you need a connection.</p>
            <button type="button" onclick="location.reload()">Try again</button>
        </main>
    </body>
</html>
