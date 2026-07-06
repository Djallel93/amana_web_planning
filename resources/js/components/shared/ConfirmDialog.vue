<!-- resources/js/components/shared/ConfirmDialog.vue -->
<!--
    Boîte de confirmation stylée, en remplacement de confirm() natif.

    Monté UNE SEULE FOIS dans le layout principal (voir app.ts,
    #vue-confirm-dialog), piloté depuis n'importe quel composant via
    useConfirm().ask({ message, danger }) — voir ce composable pour le détail
    du fonctionnement asynchrone (Promise<boolean>).
-->
<script setup lang="ts">
import { useConfirm } from '@/composables/useConfirm';
import Modal from '@/components/shared/Modal.vue';

const { state, respond } = useConfirm();
</script>

<template>
    <Modal :open="state.open" max-width="max-w-sm" @close="respond(false)">
        <template #header>
            <span v-if="state.danger" class="text-lg leading-none">⚠️</span>
            <h2 class="font-heading text-[15px] font-semibold text-ink">{{ state.title }}</h2>
        </template>

        <p class="text-[13.5px] text-ink-light leading-relaxed">{{ state.message }}</p>

        <template #footer>
            <button
                type="button"
                @click="respond(false)"
                class="px-3.5 py-2 text-[13px] font-semibold text-ink-muted hover:text-ink hover:bg-surface-3
                       rounded-lg transition-colors bg-transparent border-0 cursor-pointer min-h-[44px]">
                {{ state.cancelLabel }}
            </button>
            <button
                type="button"
                @click="respond(true)"
                class="px-3.5 py-2 text-[13px] font-bold text-white rounded-lg transition-colors border-0 cursor-pointer min-h-[44px]"
                :class="state.danger
                    ? 'bg-rose-600 hover:bg-rose-700'
                    : 'bg-accent hover:bg-accent-dark'">
                {{ state.confirmLabel }}
            </button>
        </template>
    </Modal>
</template>
