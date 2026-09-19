<h6 style="margin: 0 0 0.75rem 0; font-size: 0.95rem; font-weight: 600; color: #1e293b;">Upload Document</h6>
<div id="shareHolderDocumentUpload" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 1.25rem; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.3s ease;"
     ondrop="handleShareHolderFileDrop(event)"
     ondragover="event.preventDefault(); this.style.borderColor = '#c5a864';"
     ondragleave="this.style.borderColor = '#cbd5e1';"
     onclick="document.getElementById('shareHolderFileInput').click();">
    <!-- No name attribute: files appended in saveCustomer() / FormData -->
    <input type="file" id="shareHolderFileInput" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;" onchange="handleShareHolderFileSelect(this);">
    <i class="feather icon-upload-cloud" style="font-size: 2rem; color: #c5a864; margin-bottom: 0.35rem;"></i>
    <p style="margin: 0.35rem 0 0 0; color: #64748b; font-size: 0.85rem;">Drop files here or click to upload.</p>
</div>
<div id="shareHolderDocumentsTableWrap" style="margin-top: 1rem; overflow-x: auto;">
    <table class="table table-sm table-bordered mb-0" id="shareHolderDocumentsTable" style="font-size: 0.8rem;">
        <thead style="background: #ede9fe; color: #5b21b6;">
            <tr>
                <th style="width: 44px; text-align: center; border-color: #ddd6fe;"> </th>
                <th style="min-width: 160px; border-color: #ddd6fe;">Document Type <span style="color:#ef4444">*</span></th>
                <th style="min-width: 180px; border-color: #ddd6fe;">File Name</th>
                <th style="min-width: 140px; border-color: #ddd6fe;">Expiry Date</th>
            </tr>
        </thead>
        <tbody id="shareHolderDocumentsTableBody">
        </tbody>
    </table>
    <p id="shareHolderDocumentsEmptyHint" class="text-muted small mb-0 mt-2" style="display: none;">No documents added yet.</p>
</div>
<div id="shareHolderFileList" style="display:none;width:0;height:0;overflow:hidden;" aria-hidden="true"></div>
