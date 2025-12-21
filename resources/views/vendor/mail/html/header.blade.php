@props(['url'])

<tr>
<td class="header">
<a href="{{ config('app.url') }}" style="display: inline-block;">
    <img
        src="{{ rtrim(config('app.url'), '/') . '/image/Logo Bondwell app 2.png }}"
        alt="Bondwell"
        style="block-size:60px; inline-size:auto; display:block;"
    >
</a>
</td>
</tr>
