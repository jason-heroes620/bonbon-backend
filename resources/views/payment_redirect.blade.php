<!DOCTYPE html>
<html>
<head>
    <title>Redirecting...</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="text-align: center; font-family: sans-serif; padding-top: 50px;">
    <h2>Processing Payment...</h2>
    <p>Redirecting you back to the BonBon.</p>
    
    <!-- Fallback button: Safari sometimes REQUIRES a physical tap -->
    <a id="open-app" href="{{ $appUrl }}" 
       style="display: inline-block; padding: 12px 24px; background: #007AFF; color: white; text-decoration: none; border-radius: 8px;">
        Click here if not redirected
    </a>

    <script>
        // Attempt to open the app automatically
        window.onload = function() {
            window.location.href = "{{ $appUrl }}";
            
            // Optional: Close the window/tab after a delay if in a webview
            setTimeout(function() {
                // If they are still here, the app didn't open
                console.log("Deep link failed to trigger automatically.");
            }, 2500);
        };
    </script>
</body>
</html>