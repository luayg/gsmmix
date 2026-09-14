  // Saved local fields must not pass through provider-import normalization.
  window.__{{ $kind }}ServiceRestoreSavedFields__ = function(scope, fields){
    if (!scope || !Array.isArray(fields)) return;
    const wrap = scope.querySelector('#fieldsWrap');
    if (!wrap) return;
    const text = value => {
      if (value && typeof value === 'object' && !Array.isArray(value)) {
        return String(value.fallback ?? value.en ?? '');
      }
      return String(value ?? '');
    };
    Array.from(wrap.querySelectorAll('[data-field]')).forEach(card => card.remove());
    fields.forEach(field => {
      const options = field.options ?? field.field_options ?? '';
      addField(scope, {
        active: Number(field.active ?? 1) === 1,
        name: text(field.name),
        type: field.field_type ?? field.type ?? 'text',
        input: field.input_name ?? field.input ?? '',
        description: text(field.description),
        minimum: field.min ?? field.minimum ?? 0,
        maximum: field.max ?? field.maximum ?? 0,
        validation: field.validation ?? '',
        required: Number(field.required ?? 0),
        options: Array.isArray(options) ? options.join(',') : text(options),
      });
    });
    serializeFieldsInScope(scope);
  };
