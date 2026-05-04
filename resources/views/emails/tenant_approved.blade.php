<p>Hola {{ $request->name }},</p>

@if($domain)
    <p>La teva empresa ja està preparada. Pots entrar a: <a href="https://{{ $domain }}">{{ $domain }}</a></p>
@else
    <p>La teva empresa ha estat aprovada i ja s'ha creat la base de dades. L'administrador t'assignarà el domini pròximament.</p>
@endif

<p>Salutacions,</p>
<p>Equip</p>
