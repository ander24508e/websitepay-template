@extends('layouts.admin')

@section('title', 'Detalle Banner')

@section('content')
<div class="mx-auto w-full max-w-full overflow-x-hidden px-3 pb-4 sm:px-6">

    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('admin.banners.index') }}"
           class="flex items-center justify-center w-9 h-9 bg-white rounded-lg shadow-sm hover:bg-gray-50 transition text-gray-500 hover:text-gray-800">
            <-
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Banner #{{ $banner->id }}</h2>
            <p class="text-gray-400 text-sm">Detalle del banner</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-1 flex flex-col gap-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Imagen</p>
                <div class="flex flex-col items-center">
                    <img src="{{ $banner->imagen_url }}" alt="{{ $banner->titulo ?: 'Banner' }}"
                         class="w-56 h-40 rounded-xl object-contain bg-gray-50 border border-gray-200 shadow-sm">
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Estado</p>
                <div class="flex items-center gap-3">
                    @if($banner->activo)
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500 flex-shrink-0"></span>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Activo</p>
                            <p class="text-xs text-gray-400">Visible en el carrusel</p>
                        </div>
                    @else
                        <span class="w-2.5 h-2.5 rounded-full bg-gray-400 flex-shrink-0"></span>
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Oculto</p>
                            <p class="text-xs text-gray-400">No visible al publico</p>
                        </div>
                    @endif
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100">
                    @if($banner->es_principal)
                        <span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-medium">Banner principal</span>
                    @else
                        <span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-xs font-medium">Banner normal</span>
                    @endif
                </div>
            </div>

            @canany(['banners.update', 'banners.delete'])
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Acciones</p>
                <div class="flex flex-col gap-3">
                    @can('banners.update')
                        <a href="{{ route('admin.banners.edit', $banner) }}"
                           class="w-full bg-gray-900 text-white py-2.5 rounded-lg hover:bg-gray-700 transition font-medium text-sm text-center">
                            Editar Banner
                        </a>
                    @endcan
                    @can('banners.delete')
                        <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST"
                              onsubmit="return confirm('Eliminar este banner definitivamente?')">
                            @csrf
                            @method('DELETE')
                            <button class="w-full bg-red-50 text-red-600 py-2.5 rounded-lg hover:bg-red-100 transition font-medium text-sm border border-red-200">
                                Eliminar
                            </button>
                        </form>
                    @endcan
                </div>
            </div>
            @endcanany
        </div>

        <div class="lg:col-span-2 flex flex-col gap-6">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-5">Informacion del Banner</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Etiqueta superior</p>
                        <p class="font-semibold text-gray-800">{{ $banner->etiqueta ?: '-' }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Titulo</p>
                        <p class="font-semibold text-gray-800">{{ $banner->titulo ?: '-' }}</p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Orden</p>
                        <p class="text-gray-700">{{ $banner->orden }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-3">Texto</p>
                <p class="text-gray-700 leading-relaxed text-sm">{{ $banner->texto ?: 'Sin texto adicional.' }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Informacion del registro</p>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Creado</p>
                        <p class="text-sm text-gray-700 font-medium">{{ $banner->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Ultima actualizacion</p>
                        <p class="text-sm text-gray-700 font-medium">{{ $banner->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
