@extends('layouts.admin')

@section('title', 'Banners')

@section('content')
<div class="mx-auto w-full max-w-full overflow-x-hidden px-3 pb-4 sm:px-6">

    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <x-heroicon-o-rectangle-stack class="w-8 h-8 text-gray-800" />
                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Banners</h2>
            </div>
            <p class="text-gray-500 text-sm mt-1">Gestiona el carrusel del landing page</p>
        </div>

        <form method="GET" action="{{ route('admin.banners.index') }}" class="w-full flex-1 lg:max-w-xl">
            <div class="relative">
                <x-heroicon-o-magnifying-glass class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por etiqueta, titulo o texto del banner..." class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-10 pr-4 text-sm text-gray-700 placeholder:text-gray-400 focus:border-gray-400 focus:outline-none focus:ring-0">
            </div>
        </form>

        @can('banners.create')
            <a href="{{ route('admin.banners.create') }}"
               class="w-full bg-gray-900 text-white px-5 py-2.5 rounded-lg hover:bg-gray-700 transition font-medium text-center sm:w-auto">
                + Nuevo Banner
            </a>
        @endcan
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto overflow-y-auto max-h-[70vh]">
            <table class="min-w-[760px] w-full text-sm text-left">
                <thead class="bg-gray-50 border-b sticky top-0 z-10">
                    <tr>
                        <th class="px-4 sm:px-6 py-3 sm:py-4">Imagen</th>
                        <th class="px-4 sm:px-6 py-3 sm:py-4">Titulo</th>
                        <th class="px-4 sm:px-6 py-3 sm:py-4">Orden</th>
                        <th class="px-4 sm:px-6 py-3 sm:py-4">Principal</th>
                        <th class="px-4 sm:px-6 py-3 sm:py-4">Estado</th>
                        <th class="px-4 sm:px-6 py-3 sm:py-4">Acciones</th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    @forelse($banners as $banner)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                <img src="{{ $banner->imagen_url }}" alt="{{ $banner->titulo ?: 'Banner' }}" class="w-16 h-10 rounded object-contain bg-gray-50 border border-gray-200">
                            </td>

                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                <p class="font-medium text-gray-800">{{ $banner->titulo ?: 'Sin titulo' }}</p>
                                @if($banner->etiqueta)
                                    <p class="text-xs text-amber-600 mt-0.5">{{ $banner->etiqueta }}</p>
                                @endif
                                <p class="text-xs text-gray-400 mt-0.5">{{ \Illuminate\Support\Str::limit($banner->texto, 70) ?: 'Sin texto adicional' }}</p>
                            </td>

                            <td class="px-4 sm:px-6 py-3 sm:py-4 font-semibold text-gray-700">
                                {{ $banner->orden }}
                            </td>

                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                @if($banner->es_principal)
                                    <span class="bg-amber-100 text-amber-700 px-2 sm:px-3 py-1 rounded-full text-xs font-medium">Principal</span>
                                @else
                                    <span class="bg-gray-100 text-gray-500 px-2 sm:px-3 py-1 rounded-full text-xs font-medium">Normal</span>
                                @endif
                            </td>

                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                @if($banner->activo)
                                    <span class="bg-green-100 text-green-700 px-2 sm:px-3 py-1 rounded-full text-xs font-medium">Activo</span>
                                @else
                                    <span class="bg-gray-100 text-gray-600 px-2 sm:px-3 py-1 rounded-full text-xs font-medium">Oculto</span>
                                @endif
                            </td>

                            <td class="px-4 sm:px-6 py-3 sm:py-4">
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('admin.banners.show', $banner) }}"
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium px-2 py-1">
                                        Ver
                                    </a>
                                    @can('banners.update')
                                        <a href="{{ route('admin.banners.edit', $banner) }}"
                                           class="text-yellow-600 hover:text-yellow-800 text-sm font-medium px-2 py-1">
                                            Editar
                                        </a>
                                    @endcan
                                    @can('banners.delete')
                                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}"
                                              onsubmit="return confirm('Eliminar este banner?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:text-red-800 text-sm font-medium px-2 py-1">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-10 text-gray-400">No hay banners registrados</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($banners->hasPages())
            <div class="p-4 border-t">
                {{ $banners->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
