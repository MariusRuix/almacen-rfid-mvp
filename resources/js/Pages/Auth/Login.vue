<script setup lang="ts">
import SelectorTema from '@/Components/SelectorTema.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ScanLine } from 'lucide-vue-next';

const props = defineProps<{ usuariosDemo: { rol: string; email: string }[]; passwordDemo: string }>();

const form = useForm({ email: '', password: '' });

function usar(email: string) {
    form.email = email;
    form.password = props.passwordDemo;
}

function entrar() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Entrar" />
    <div class="grid min-h-dvh lg:grid-cols-[1fr_minmax(26rem,32rem)]">
        <section class="hidden flex-col justify-between bg-rail p-12 text-rail-ink lg:flex">
            <p class="font-display text-2xl font-bold">Almacén RFID</p>
            <div class="max-w-[34rem]">
                <h1 class="font-display text-[2.6rem] leading-[1.05] font-bold">De la recepción a la salida, con el pedimento a la vista.</h1>
                <p class="mt-4 text-lg text-rail-muted">
                    Recorrido de demostración: lo esperado contra lo confirmado en cada paso, por ubicación, pedimento y destino.
                </p>
            </div>
            <div class="flex items-center justify-between gap-6">
                <p class="text-sm text-rail-muted">Todos los datos son ficticios.</p>
                <div class="w-56"><SelectorTema /></div>
            </div>
        </section>

        <section class="flex items-center justify-center px-5 py-12">
            <div class="w-full max-w-sm">
                <div class="mb-8 lg:hidden">
                    <p class="font-display text-2xl font-bold">Almacén RFID</p>
                    <p class="text-ink-2">Demostración con datos ficticios</p>
                </div>
                <h2 class="text-2xl font-bold">Entrar</h2>

                <form class="mt-6 space-y-4" @submit.prevent="entrar">
                    <div>
                        <label for="email" class="etiqueta">Correo</label>
                        <input id="email" v-model="form.email" type="email" class="campo" autocomplete="username" required autofocus />
                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-bad">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label for="password" class="etiqueta">Contraseña</label>
                        <input id="password" v-model="form.password" type="password" class="campo" autocomplete="current-password" required />
                    </div>
                    <button type="submit" class="btn btn-primario w-full" :disabled="form.processing">
                        {{ form.processing ? 'Entrando…' : 'Entrar' }}
                    </button>
                </form>

                <div class="mt-8 border-t border-line pt-5">
                    <p class="text-sm font-semibold text-ink-2">Usuarios de prueba</p>
                    <ul class="mt-2 space-y-2">
                        <li v-for="u in usuariosDemo" :key="u.email">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-ui border border-line bg-surface px-3 py-2.5 text-left hover:border-line-strong"
                                @click="usar(u.email)"
                            >
                                <ScanLine class="size-4 flex-none text-ink-3" aria-hidden="true" />
                                <span class="flex-1">
                                    <span class="block text-sm font-semibold">{{ u.rol }}</span>
                                    <span class="block font-id text-xs text-ink-2">{{ u.email }}</span>
                                </span>
                                <span class="text-xs font-semibold text-primary">Usar</span>
                            </button>
                        </li>
                    </ul>
                    <p class="mt-2 text-xs text-ink-3">Contraseña: <span class="font-id">{{ passwordDemo }}</span></p>
                </div>
            </div>
        </section>
    </div>
</template>
