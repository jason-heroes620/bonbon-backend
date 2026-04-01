<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Verify your email</title>
    </head>
    <body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #111827;">
        <div style="text-align: center; margin-bottom: 18px;">
            <img src="{{ url('/bonbon-logo.png') }}" alt="BonBon" style="max-width: 150px; height: auto;" />
        </div>
        <h2 style="margin: 0 0 12px 0; font-size: 20px;">Verify your email</h2>
        <p style="margin: 0 0 16px 0; line-height: 1.5;">
            Hi {{ $name }}, please verify your email to activate your account.
        </p>
        <div style="margin: 18px 0;">
            <a href="{{ $url }}" style="display: inline-block; background: #F90606; color: #ffffff; padding: 12px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                Verify email
            </a>
        </div>
        <p style="margin: 0 0 10px 0; font-size: 12px; color: #6B7280; line-height: 1.5;">
            If you did not create an account, you can ignore this email.
        </p>
        <p style="margin: 0; font-size: 12px; color: #6B7280; word-break: break-all;">
            Or copy and paste this link into your browser: {{ $url }}
        </p>
    </body>
</html>
