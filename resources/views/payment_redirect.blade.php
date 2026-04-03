<!DOCTYPE html>
<html>
<head>
    <!-- 1. The Meta Refresh (Stronger than JS for some Safari versions) -->
    <meta http-equiv="refresh" content="0;url={{ $appUrl }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Returning to BonBon</title>
</head>
<body style="text-align: center; font-family: -apple-system; padding: 50px 20px;">
    <h3>Payment Complete</h3>
    <p>We are opening the BonBon app for you...</p>

    <!-- 2. The "Hidden" Link Trick -->
    <!-- Sometimes Safari ignores a redirect but allows a 'click' simulation -->
    <a id="auto-link" href="{{ $appUrl }}" style="display:none;"></a>

    <button onclick="window.location.href='{{ $appUrl }}'" 
            style="padding: 15px 30px; background: #007AFF; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: bold;">
        Open BonBon App Manually
    </button>

    <script>
        if (window.top !== window.self) {
            window.top.location.href = "{{ $appUrl }}";
        }

        window.onload = function() {
            // Attempt 1: Direct location change
            window.location.href = "{{ $appUrl }}";
            
            // Attempt 2: Simulated Click (more 'trusted' by Safari)
            setTimeout(function() {
                document.getElementById('auto-link').click();
            }, 50);

            // Attempt 3: If still on page after 2 seconds, show the button clearly
            setTimeout(function() {
                console.log("Redirect might have been blocked.");
            }, 2000);
        };
    </script>
</body>
</html>