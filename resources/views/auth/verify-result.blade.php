<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <meta name="theme-color" content="#F90606" />
    <title>{{ $title ?? 'BonBon' }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(180deg, #F90606 0%, #F90606 40%, #F8FAFC 40%, #F8FAFC 100%);
            color: #1E293B;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            min-height: 100vh;
        }

        .card {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 32px 24px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.12);
        }

        .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            margin-bottom: 16px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            margin: 0 auto 16px;
            font-size: 28px;
        }

        .badge.success {
            background: rgba(34, 197, 94, 0.12);
            color: #16A34A;
        }

        .badge.error {
            background: rgba(239, 68, 68, 0.12);
            color: #DC2626;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .message {
            font-size: 15px;
            color: #64748B;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 52px;
            background: #F90606;
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.05s ease;
            text-decoration: none;
        }

        .btn:active {
            transform: scale(0.98);
            background: #DC0505;
        }

        .fallback {
            display: none;
            margin-top: 14px;
            font-size: 13px;
            color: #94A3B8;
            line-height: 1.5;
        }

        .fallback a {
            color: #F90606;
            font-weight: 600;
            text-decoration: none;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #CBD5E1;
        }
    </style>
</head>
<body>
    <div class="card">
        <img src="{{ url('/bonbon-logo.png') }}" alt="BonBon" class="logo" />

        <div class="badge {{ $status === 'success' ? 'success' : 'error' }}">
            @if ($status === 'success')
                &#10003;
            @else
                &#9888;
            @endif
        </div>

        <h1>{{ $title }}</h1>
        <p class="message">{{ $message }}</p>

        <button type="button" class="btn" id="closeBtn" onclick="closeWindow()">
            {{ $status === 'success' ? 'Close' : 'Close' }}
        </button>

        <p class="fallback" id="fallback">
            Couldn't close this window automatically.
            <a href="{{ $deepLink ?? 'bonbon://' }}" id="openApp">Open BonBon App</a> or close this tab manually.
        </p>

        <div class="footer">&copy; {{ date('Y') }} BonBon</div>
    </div>

    <script>
        function closeWindow() {
            var btn = document.getElementById('closeBtn');
            if (btn) btn.disabled = true;

            // Attempt to close the window/tab.
            // This works on desktop for script-opened windows and some browsers.
            try {
                window.open('', '_self');
                window.close();
            } catch (e) {}

            // Mobile browsers block window.close() for tabs not opened by script.
            // After a short delay, if we're still on the page, show the fallback
            // (deep-link back to the BonBon app).
            setTimeout(function () {
                document.getElementById('fallback').style.display = 'block';
                window.location.href = "{{ $deepLink ?? 'bonbon://' }}";
            }, 400);
        }
    </script>
</body>
</html>
