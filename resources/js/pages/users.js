/* resources/js/pages/users.js */
import $ from "jquery";

$(function () {
  const $page = $("#usersPage");
  if (!$page.length) return;

  const urls = {
    data: $page.data("url-data"),
    showBase: $page.data("url-show-base"),
    store: $page.data("url-store"),
    updateBase: $page.data("url-update-base"),
    destroyBase: $page.data("url-destroy-base"),
    roles: $page.data("url-roles"),
    groups: $page.data("url-groups"),
  };

  // Bootstrap 5 UMD
  const bs = window.bootstrap;

  // ===== helper =====
  const token = $('meta[name="csrf-token"]').attr("content");
  const withId = (base, id) => String(base).replace(/0$/, String(id));

  const initSelect2In = ($ctx) => {
    // groups
    $ctx.find("select.js-groups").each(function () {
      const $el = $(this);
      if ($el.data("select2")) $el.select2("destroy");
      $el.select2({
        theme: "bootstrap-5",
        width: "100%",
        dropdownParent: $ctx,
        placeholder: $el.data("placeholder") || "Select group",
        allowClear: true,
        ajax: {
          url: urls.groups,
          delay: 200,
          data: (params) => ({ q: params.term || "" }),
          processResults: (data) => data,
        },
      });
    });

    // roles
    $ctx.find('select.js-roles').each(function () {
      const $el = $(this);
      if ($el.data("select2")) $el.select2("destroy");
      $el.select2({
        theme: "bootstrap-5",
        width: "100%",
        multiple: true,
        dropdownParent: $ctx,
        placeholder: $el.data("placeholder") || "Select roles",
        ajax: {
          url: urls.roles,
          delay: 200,
          data: (params) => ({ q: params.term || "" }),
          processResults: (data) => data,
        },
      });
    });
  };

  // ===== DataTable =====
  const $table = $("#usersTable");
  const dt = $table.DataTable({
    processing: true,
    serverSide: true,
    responsive: true,
    ajax: {
      url: urls.data,
      type: "GET",
      error: (xhr) => {
        console.error("DataTable AJAX error:", xhr?.responseText || xhr.statusText);
        alert("فشل تحميل البيانات. راجع الـ Console.");
      },
    },
    order: [[0, "asc"]],
    columns: [
      { data: "id", name: "id" },
      { data: "name", name: "name" },
      { data: "email", name: "email" },
      { data: "username", name: "username" },
      { data: "roles", name: "roles", orderable: false, searchable: false },
      { data: "group", name: "group", orderable: false, searchable: false },
      { data: "balance", name: "balance", className: "text-end" }, // ← بدلاً من created
      { data: "status", name: "status" },
      { data: "actions", name: "actions", orderable: false, searchable: false },
    ],
  });

  const refreshTable = () => dt.ajax.reload(null, false);

  // ===== View =====
  $(document).on("click", ".btn-view-user", function () {
    const id = $(this).data("id");
    $.getJSON(withId(urls.showBase, id))
      .done((res) => {
        const $m = $("#modalViewUser");
        const set = (k, v) => $m.find(`[data-k="${k}"]`).text(v ?? "-");
        set("name", res.name);
        set("email", res.email);
        set("username", res.username || "-");
        set("group", res.group || "-");
        set("roles", (res.roles || []).join(", ") || "-");
        set("status", res.status);
        set("created", res.created);
        new bs.Modal($m[0]).show();
      })
      .fail((xhr) => {
        console.error(xhr.responseText);
        alert("فشل عرض بيانات المستخدم.");
      });
  });

  // ===== Create =====
  $("#btnOpenCreate").on("click", function () {
    const $m = $("#modalCreateUser");
    const f = document.getElementById("formCreateUser");
    f.reset();
    $m.find("select.js-groups").empty();
    $m.find("select.js-roles").empty();
    initSelect2In($m);
    new bs.Modal($m[0]).show();
  });

  $("#formCreateUser").on("submit", function (e) {
    e.preventDefault();
    const $m = $("#modalCreateUser");
    $.ajax({
      url: urls.store,
      type: "POST",
      data: $(this).serialize(),
      headers: { "X-CSRF-TOKEN": token },
    })
      .done(() => {
        bs.Modal.getInstance($m[0]).hide();
        refreshTable();
      })
      .fail((xhr) => {
        console.error(xhr.responseText);
        alert("فشل حفظ المستخدم (Create).");
      });
  });

  // ===== Edit (open) =====
  $(document).on("click", ".btn-edit-user", function () {
    const id = $(this).data("id");
    const $m = $("#modalEditUser");
    const $f = $("#formEditUser");

    $.getJSON(withId(urls.showBase, id))
      .done((res) => {
        $f.find('input[name="user_id"]').val(id);
        $f.find('input[name="name"]').val(res.name || "");
        $f.find('input[name="email"]').val(res.email || "");
        $f.find('input[name="username"]').val(res.username || "");
        $f.find('select[name="status"]').val(res.status || "active");

        // group
        const $g = $f.find('select[name="group_id"]');
        $g.empty();

        // roles
        const $r = $f.find('select[name="roles[]"]');
        $r.empty();
        (res.roles || []).forEach((name) => {
          const opt = new Option(name, name, true, true);
          $r.append(opt);
        });

        initSelect2In($m);
        new bs.Modal($m[0]).show();
      })
      .fail((xhr) => {
        console.error(xhr.responseText);
        alert("فشل تحميل بيانات المستخدم.");
      });
  });

  // ===== Edit (submit) =====
  $("#formEditUser").on("submit", function (e) {
    e.preventDefault();
    const $m = $("#modalEditUser");
    const id = $(this).find('input[name="user_id"]').val();
    $.ajax({
      url: withId(urls.updateBase, id),
      type: "POST", // مع @method('PUT') داخل النموذج
      data: $(this).serialize(),
      headers: { "X-CSRF-TOKEN": token },
    })
      .done(() => {
        bs.Modal.getInstance($m[0]).hide();
        refreshTable();
      })
      .fail((xhr) => {
        console.error(xhr.responseText);
        alert("فشل تحديث المستخدم (Edit).");
      });
  });

  // ===== Delete =====
  $(document).on("click", ".btn-del-user", function () {
    const id = $(this).data("id");
    const name = $(this).data("name") || "";
    if (!confirm(`Delete user "${name}"?`)) return;

    $.ajax({
      url: withId(urls.destroyBase, id),
      type: "POST",
      data: { _method: "DELETE" },
      headers: { "X-CSRF-TOKEN": token },
    })
      .done(() => refreshTable())
      .fail((xhr) => {
        console.error(xhr.responseText);
        alert("فشل حذف المستخدم.");
      });
  });
});
