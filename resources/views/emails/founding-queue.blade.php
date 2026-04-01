<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BonBon Founding Queue</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #111827; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
        .logo-wrap { text-align: center; margin: 0 0 16px; }
        .logo { display: inline-block; max-width: 180px; width: 100%; height: auto; }
        .title { font-size: 20px; font-weight: 700; margin: 0 0 12px; }
        .p { margin: 8px 0; line-height: 1.6; }
        .list { margin: 12px 0; padding-left: 18px; }
        .footer { color: #6b7280; font-size: 12px; margin-top: 16px; }
        .strong { font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo-wrap">
                <img class="logo" src="{{ url('/bonbon-logo.png') }}" alt="BonBon" />
            </div>
            <p class="title">Hi {{ $name }}! 🐾 You're officially in the BonBon founding member queue!</p>
            <p class="p">Here's what to expect:</p>
            <ul class="list">
                <li class="p">🗓 1 week before <span class="strong">{{ $privateLaunchDate }}</span> — you'll receive a download link via WhatsApp and email</li>
                <li class="p">⏳ You'll have 5 days to download the app and join BonBon Standard Membership at RM99 to lock in your founding member spot</li>
                <li class="p">❗ If you don't join BonBon Standard Membership within 5 days, your spot goes to the next person in line</li>
                <li class="p">✅ After that, you can still join at RM139/year</li>
            </ul>
            <p class="p">⚠️ Important: When creating your BonBon account in the app, use this exact email: <span class="strong">{{ $registeredEmail }}</span>. This is how your founding price is verified.</p>
            <p class="p">Keep an eye on your WhatsApp — your link is coming soon 🚀</p>
            <p class="p">BonBon App · <a href="https://www.bonbon.com.my/" target="_blank">BonBon</a></p>
        </div>
        <p class="footer">If you did not request this, you can ignore this email.</p>
    </div>
</body>
</html>
