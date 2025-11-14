<!-- resources/views/emails/session_link.blade.php -->
<!doctype html>
<html>
  <body style="font-family:Arial,Helvetica,sans-serif">
    <h2>Your therapy session is ready</h2>
    <p>
      Session #: {{ $session->id }}<br>
      Date: {{ $session->scheduled_at->format('D, M j, Y') }}<br>
      Time: {{ $session->scheduled_at->format('h:i A') }}<br>
      Duration: {{ $session->duration_min }} minutes
    </p>
    <p>
      <a href="{{ $joinUrl }}">Join via Zoom</a>
    </p>
    <p>If the button doesn’t work, copy this URL:<br>{{ $joinUrl }}</p>
  </body>
</html>
