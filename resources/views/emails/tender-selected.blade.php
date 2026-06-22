<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compartment Selection Notification</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #111827; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; }
        .card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
        .logo-wrap { text-align: center; margin: 0 0 16px; }
        .logo { display: inline-block; max-width: 180px; width: 100%; height: auto; }
        .title { font-size: 20px; font-weight: 700; margin: 0 0 12px; }
        .p { margin: 8px 0; line-height: 1.6; }
        .details { margin: 16px 0; padding: 16px; background: #f9fafb; border-radius: 10px; }
        .row { margin: 8px 0; }
        .label { font-weight: 600; }
        .footer { color: #6b7280; font-size: 12px; margin-top: 16px; }
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px 40px;
            text-align: left;
            border-top: 1px solid #e9ecef;
        }
        .email-address {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .footer-text {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo-wrap">
                <img class="logo" src="{{ url('/bonbon-logo.png') }}" alt="BonBon" />
            </div>

            <p class="title">Hi {{ $details['vendor_name'] }}, your compartment bid has been selected.</p>

            <p class="p">
                You have been selected for the following compartment and will need to make payment before the contract expiry date.
            </p>

            <div class="details">
                <div class="row"><span class="label">Location:</span> {{ $details['vendor_location_name'] }}</div>
                <div class="row"><span class="label">Rack:</span> {{ $details['rack_name'] }}</div>
                <div class="row"><span class="label">Compartment:</span> {{ $details['compartment_label'] }}</div>
                <div class="row"><span class="label">Bid Price:</span> RM {{ $details['bid_price'] }}</div>
                <div class="row"><span class="label">Duration:</span> {{ $details['durations'] }} months</div>
                <div class="row"><span class="label">Payment Due By:</span> {{ $details['tender_end_date'] }}</div>
            </div>

            <p class="p">
                If payment is not completed before <strong>{{ $details['tender_end_date'] }}</strong>, the contract will expire and the compartment will be made available again.
            </p>

            <p class="p">
                Please log in to BonBon and go to <strong>My Contracts</strong> to complete your payment.
            </p>
        </div>
        <p class="footer">If you did not expect this notification, please contact the BonBon team.</p>

        <div class="email-footer">
            <span  class="footer-text">Sincerely,</span><br>
            <span  class="footer-text">BonBon Event Team</span>
            <p></p>
           <div class="email-address">
                <div>
                    <span  class="footer-text">Email: </span><br>
                    <span class="footer-text">hello@bonbon.com.my</span>
                    <span  class="footer-text">Contact No.: </span><br>
                    <span class="footer-text">012 7456 750</span>
                </div>

                <div>
                    <span class="footer-text">Address:</span><br>
                    <span class="footer-text">Suite 9.01, Menara Summit</span><br>
                    <span class="footer-text">Persiaran Kewajipan, USJ 1,</span><br>
                    <span class="footer-text">UEP, 47600 Subang Jaya,</span><br>
                    <span class="footer-text">Selangor</span><br>
                </div>
           </div>
        </div>
    </div>
</body>
</html>
