<?php
/* /console/reservas/modal_form.php */
if (!defined('RESERVAS_MODAL')) define('RESERVAS_MODAL', 1);
?>
<!-- Modal crear/editar -->
<div id="reserva-modal" class="fixed inset-0 bg-black/50 hidden opacity-0 transition-opacity z-50">
  <div id="reserva-modal-card" class="bg-[var(--card-bg)] w-full max-w-lg mx-auto mt-16 p-6 rounded-xl shadow-lg transform transition-transform scale-95">
    <div class="flex justify-between items-center mb-4">
      <h2 id="modal-title" class="text-xl font-bold">Nueva Reserva</h2>
      <button id="close-modal-btn" class="text-[var(--text-secondary)] hover:text-[var(--text-primary)]"><i class="fas fa-times fa-lg"></i></button>
    </div>

    <form id="reserva-form" autocomplete="off">
      <input type="hidden" id="reserva-id" name="id">
      <input type="hidden" id="is-bloqueo" name="is_bloqueo" value="0">

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Valla <span class="text-red-500">*</span></label>
          <select id="valla-select" name="valla_id" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
            <option value="">Seleccione una valla</option>
          </select>
        </div>

        <div id="cliente-wrap">
          <label class="block text-sm font-medium mb-1">Nombre del Cliente <span class="text-red-500">*</span></label>
          <input type="text" id="cliente-nombre" name="nombre_cliente" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Fecha Inicio <span class="text-red-500">*</span></label>
            <input type="text" id="fecha-inicio" name="fecha_inicio" placeholder="YYYY-MM-DD" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Fecha Fin <span class="text-red-500">*</span></label>
            <input type="text" id="fecha-fin" name="fecha_fin" placeholder="YYYY-MM-DD" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Tipo</label>
          <select id="estado-select" name="estado" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]">
            <option value="pendiente">Pendiente</option>
            <option value="confirmada">Confirmada</option>
            <option value="bloqueo">Bloqueo (No disponible)</option>
          </select>
          <p class="text-xs text-[var(--text-secondary)] mt-1">“Bloqueo” crea un periodo no disponible en lugar de una reserva.</p>
        </div>

        <div id="motivo-wrap" class="hidden">
          <label class="block text-sm font-medium mb-1">Motivo del bloqueo</label>
          <input type="text" id="motivo-bloqueo" name="motivo" class="w-full px-4 py-2 border rounded-lg bg-[var(--input-bg)] border-[var(--border-color)]" placeholder="Ej: Mantenimiento">
        </div>
      </div>

      <div class="mt-6 flex justify-end gap-3">
        <button type="button" id="cancel-reserva-btn" class="px-4 py-2 bg-gray-200 text-gray-700 dark:bg-gray-600 dark:text-gray-200 rounded-lg">Cancelar</button>
        <button type="submit" id="save-reserva-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg">Guardar</button>
        <button type="button" id="delete-reserva-btn" class="hidden px-4 py-2 bg-red-600 text-white rounded-lg">Eliminar</button>
      </div>
    </form>
  </div>
</div>
