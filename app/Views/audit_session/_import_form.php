<form method="post" action="/audit-session/import" enctype="multipart/form-data">
    <input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>">

    <p>
        <strong>Arquivo (CSV ou XLSX):</strong><br>
        <input type="file" name="file">
    </p>

    <p><strong>OU texto colado:</strong></p>

    <p>
        <textarea name="raw_text" rows="6" cols="80"
            placeholder="Cole aqui o texto dos chamados (um ou vários)..."></textarea>
    </p>

    <button type="submit">Importar chamados</button>
</form>