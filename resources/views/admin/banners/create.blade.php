@extends('layouts.admin')

@section('title', 'Nuevo Banner')

@section('content')
@php
    $bannersReturnUrl = auth()->user()->can('banners.view')
        ? route('admin.banners.index')
        : route('home');
@endphp
<div class="mx-auto w-full max-w-full overflow-x-hidden px-3 pb-4 sm:px-6">
    <div class="flex flex-wrap items-center gap-3 mb-6">
        <a href="{{ $bannersReturnUrl }}"
           class="flex items-center justify-center w-9 h-9 bg-white rounded-lg shadow-sm hover:bg-gray-50 transition text-gray-500 hover:text-gray-800">
            <-
        </a>

        <div>
            <div class="flex items-center gap-2">
                <x-heroicon-o-rectangle-stack class="w-8 h-8 text-gray-800" />
                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Banners</h2>
            </div>
            <p class="text-gray-400 text-sm">Crea una nueva diapositiva para el landing page</p>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6">

        <div class="w-full lg:w-1/3 space-y-6">
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Imagen del Banner</p>

                <div class="flex flex-col items-center mb-4">
                    <div id="img-preview"
                         class="w-28 h-28 sm:w-32 sm:h-32 bg-gray-100 rounded-xl flex items-center justify-center text-4xl mb-3 overflow-hidden border-2 border-dashed border-gray-200">
                        BNR
                    </div>
                    <p class="text-xs text-gray-400 text-center" id="img-name">Sin imagen seleccionada</p>
                </div>

                <input type="file" name="imagen" id="image-input" accept="image/*" form="banner-form" class="hidden" onchange="previewImage(this)">

                <button type="button" onclick="document.getElementById('image-input').click()"
                        class="w-full bg-gray-900 text-white py-2.5 rounded-lg hover:bg-gray-700 transition font-medium text-sm">
                    Subir Imagen
                </button>
                <p class="text-xs text-gray-400 text-center mt-2">JPG, PNG o WEBP - Max. 6MB. Opcional para portada principal.</p>

                @error('imagen')
                    <p class="text-red-500 text-xs mt-2 text-center">{{ $message }}</p>
                @enderror
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Visibilidad</p>
                <label class="flex items-center gap-3 cursor-pointer">
                    <div class="relative">
                        <input type="hidden" name="activo" value="0" form="banner-form">
                        <input type="checkbox" name="activo" value="1" id="active-toggle" form="banner-form"
                               {{ old('activo', true) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-checked:bg-green-500 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Banner activo</p>
                        <p class="text-xs text-gray-400">Visible en el carrusel</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 cursor-pointer mt-4">
                    <div class="relative">
                        <input type="hidden" name="es_principal" value="0" form="banner-form">
                        <input type="checkbox" name="es_principal" value="1" id="primary-toggle" form="banner-form"
                               {{ old('es_principal', false) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-checked:bg-amber-500 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Banner principal</p>
                        <p class="text-xs text-gray-400">Se usara como portada inicial. Los demas dejan de ser principal.</p>
                    </div>
                </label>
            </div>
        </div>

        <div class="w-full lg:w-2/3">
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-5">Informacion del Banner</p>

                <form id="banner-form" action="{{ route('admin.banners.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Etiqueta superior</label>
                        <input type="text" name="etiqueta" value="{{ old('etiqueta') }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 bg-gray-50 @error('etiqueta') border-red-400 bg-red-50 @enderror"
                               placeholder="Ej: Servicio destacado">
                        @error('etiqueta')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titulo</label>
                        <input type="text" name="titulo" value="{{ old('titulo') }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 bg-gray-50 @error('titulo') border-red-400 bg-red-50 @enderror"
                               placeholder="Ej: Lavado Premium esta semana">
                        @error('titulo')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Texto</label>
                        <textarea name="texto" rows="4"
                                  class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 bg-gray-50 resize-none @error('texto') border-red-400 bg-red-50 @enderror"
                                  placeholder="Agrega un mensaje corto para el banner">{{ old('texto') }}</textarea>
                        @error('texto')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
                        <input type="number" name="orden" min="0" max="9999" value="{{ old('orden', $nextOrder ?? 0) }}"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 bg-gray-50 @error('orden') border-red-400 bg-red-50 @enderror">
                        @error('orden')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2 border-t border-gray-100">
                        <button type="submit"
                                class="bg-gray-900 text-white px-6 py-2.5 rounded-lg hover:bg-gray-700 transition font-medium text-sm">
                            Guardar Banner
                        </button>
                        <a href="{{ $bannersReturnUrl }}"
                           class="bg-gray-100 text-gray-600 px-6 py-2.5 rounded-lg hover:bg-gray-200 transition font-medium text-sm text-center">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function previewImage(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    if (file.size > 6 * 1024 * 1024) {
        alert('La imagen no debe superar 6MB.');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = e => {
        const preview = document.getElementById('img-preview');
        preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-contain bg-gray-50">`;
    };
    reader.readAsDataURL(file);
    document.getElementById('img-name').textContent = file.name;
}
</script>
@endpush
