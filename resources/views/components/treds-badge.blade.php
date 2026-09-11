@props(['treds'])

<span {{ $attributes->merge(['class' => 'pkg-badge pkg-badge--'.$treds->tone()]) }}>{{ $treds->label() }}</span>
