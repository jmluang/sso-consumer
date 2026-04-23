@props(['label' => null, 'class' => null, 'portalUrl'])

<a href="{{ $portalUrl }}"
   class="{{ $class ?? 'inline-flex items-center justify-center px-4 py-2 rounded-md bg-black text-white text-sm font-medium hover:bg-gray-800' }}">
    {{ $label }}
</a>
