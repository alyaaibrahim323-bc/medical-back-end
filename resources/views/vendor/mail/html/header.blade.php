@props(['url'])

<tr>
<td class="header">
<a href="{{ config('app.url') }}" style="display: inline-block;">
    <img
        src="{{ rtrim(config('app.url'), '/') . '/image/Logo.png' }}"
        alt="Bondwell"
        style="height:60px; width:auto; display:block;"
    >
</a>
</td>
</tr>
