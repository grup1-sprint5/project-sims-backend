<p>New tenant registration request received:</p>
<ul>
    <li><strong>Name:</strong> {{ $request->name }}</li>
    <li><strong>Slug:</strong> {{ $request->slug }}</li>
    <li><strong>Email:</strong> {{ $request->email }}</li>
    <li><strong>Notes:</strong> {{ $request->notes }}</li>
    <li><strong>Requested at:</strong> {{ $request->requested_at }}</li>
</ul>

<p>Go to the admin panel to review and approve the request.</p>
