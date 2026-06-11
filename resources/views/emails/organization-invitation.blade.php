<!DOCTYPE html>
<html>
<head>
    <title>
        Organization Invitation
    </title>
</head>
<body>
    <h2>
        You've been invited to join
        {{ $invitation->organization->name }}
    </h2>

    <p>
        Role:
        {{ ucfirst($invitation->role) }}
    </p>

    <p>
        Click below to accept:
    </p>

    <a href="{{ url('/invitations/' . $invitation->token) }}">
        Accept Invitation
    </a>
</body>
</html>
