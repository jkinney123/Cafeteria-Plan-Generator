<?php
if (!defined('ABSPATH'))
    exit;

use Dompdf\Dompdf;

/**
 * 12) PDF Generation
 */
function cpp_wizard_generate_pdf($caf_plan_id)
{
    if (!$caf_plan_id || get_post_type($caf_plan_id) !== 'cafeteria_plan') {
        wp_die('Invalid or missing plan ID.');
    }
    error_log('DEBUG: Entered cpp_wizard_generate_pdf function.');

    if (ob_get_length()) {
        ob_end_clean();
    }
    ob_clean();

    $dompdf = new Dompdf();

    // Gather data from postmeta
    $company_name = get_post_meta($caf_plan_id, '_cpp_company_name', true);
    $effective_date = get_post_meta($caf_plan_id, '_cpp_effective_date', true);
    $plan_details = get_post_meta($caf_plan_id, '_cpp_plan_details', true);
    $special_req = get_post_meta($caf_plan_id, '_cpp_special_requirements', true);

    $include_cobra = get_post_meta($caf_plan_id, '_cpp_include_cobra', true);
    $include_fsa = get_post_meta($caf_plan_id, '_cpp_include_fsa', true);
    $benefits_str = get_post_meta($caf_plan_id, '_cpp_benefits_included', true);
    $benefits_arr = array_filter(explode(',', $benefits_str));

    // Convert to safe HTML
    $company_name = esc_html($company_name);
    $effective_date = esc_html($effective_date);
    $plan_details = esc_html($plan_details);
    $special_req = esc_html($special_req);

    // Let's load library in case we want to conditionally add text
    $library = cpp_load_plan_library();

    update_post_meta($caf_plan_id, '_cpp_status', 'Finalized');
    update_post_meta($caf_plan_id, '_cpp_last_edited', current_time('mysql'));

    $template_version = get_post_meta($caf_plan_id, '_cpp_template_version', true) ?: 'v1';
    $template_data = cpp_get_template_versions();
    $html = cpp_build_full_doc_html($caf_plan_id, $template_data, $template_version, false); // false = not redline

    error_log('DEBUG: HTML for PDF => ' . $html);

    try {
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();
        $length = strlen($pdfOutput);
        error_log('DEBUG: PDF length = ' . $length);

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="cafeteria-plan.pdf"');
        header('Accept-Ranges: none');

        echo $pdfOutput;
    } catch (\Exception $e) {
        error_log('DOMPDF ERROR: ' . $e->getMessage());
        echo '<p>Sorry, an error occurred generating the PDF: ' . esc_html($e->getMessage()) . '</p>';
    }
    exit;

}

function cpp_build_intro_header($company_name, $effective_date, $plan_options_selected)
{
    $component_titles = [
        'Pre-Tax Premiums' => 'PREMIUM PAYMENT ARRANGEMENT',
        'Health Savings Account (HSA)' => 'HEALTH SAVINGS ACCOUNT',
        'Health Flexible Spending Account (Health FSA)' => 'HEALTH FLEXIBLE SPENDING ARRANGEMENT',
        'Dependent Care Account' => 'DEPENDENT CARE ASSISTANCE PLAN',
    ];

    $components = [];
    foreach ($plan_options_selected as $option) {
        $option = trim($option);
        if (isset($component_titles[$option])) {
            $components[] = $component_titles[$option];
        }
    }

    // Start of intro page
    $header_html = '<div style="page-break-after: always;">';

    // Company/Cover Page Heading
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; font-weight: bold; margin-top: 120pt;">'
        . strtoupper($company_name) . '</div>';

    // Intro Title Line
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; font-weight: normal; margin-top: 24pt;">'
        . 'CAFETERIA PLAN WITH</div>';

    // Component Lines
    $count = count($components);
    foreach ($components as $i => $comp) {
        $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; text-transform: uppercase; margin-top: 6pt;">' . $comp . '</div>';
        if ($count > 1 && $i === $count - 2) {
            $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; margin-top: 6pt;">AND</div>';
        }
    }

    // Final line: "COMPONENTS"
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; margin-top: 12pt;">COMPONENTS</div>';

    // Footer date line
    $header_html .= '<div style="text-align: center; font-family: Times New Roman; font-size: 12pt; font-weight: bold; margin-top: 36pt;">'
        . 'As Amended and Restated ' . esc_html($effective_date) . '</div>';

    // Close page
    $header_html .= '</div>';

    return $header_html;
}

function cpp_build_full_doc_html($plan_id, $template_data, $version, $redline = false, $old_version = null, $is_preview = false)
{
    // Fetch demographic tokens
    $company_name = esc_html(get_post_meta($plan_id, '_cpp_company_name', true));
    $effective_date = esc_html(get_post_meta($plan_id, '_cpp_effective_date', true));
    $plan_options_selected_str = get_post_meta($plan_id, '_cpp_plan_options', true);
    $plan_options_selected = array_filter(explode(',', $plan_options_selected_str));

    $preview_css = '';
    if ($is_preview) {
        $preview_css = '
        .pdf-preview-scroll-container {
            height: 85vh;
            max-height: 1200px;
            min-height: 800px;
            overflow-y: auto;
            overflow-x: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f5f5f5;
            padding: 20px;
            margin: 20px auto;
            width: 100%;
            max-width: 960px;
            min-width: 856px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }
        .pdf-preview-expand-btn {
            position: sticky;
            top: 1px;
            right: 1px;
            float: right;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 12px;
            z-index: 20;
            transition: background 0.2s ease;
        }
        .pdf-preview-expand-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        .pdf-preview-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            padding: 10px;
            box-sizing: border-box;
        }
        .pdf-preview-modal-overlay.active {
            display: flex;
        }
        .pdf-preview-modal-container {
            width: 95%;
            max-width: 1100px;
            min-width: 900px;
            height: 95vh;
            max-height: 95vh;
            background: #f5f5f5;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        .pdf-preview-modal-header {
            background: #333;
            color: white;
            padding: 15px 20px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pdf-preview-modal-title {
            font-weight: bold;
            font-size: 16px;
        }
        .pdf-preview-close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.2s ease;
        }
        .pdf-preview-close-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .pdf-preview-modal-content {
            height: calc(100% - 60px);
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px;
            background: #f5f5f5;
        }
        .pdf-preview-modal-content::-webkit-scrollbar {
            width: 12px;
        }
        .pdf-preview-modal-content::-webkit-scrollbar-track {
            background: #e1e1e1;
            border-radius: 6px;
        }
        .pdf-preview-modal-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 6px;
        }
        .pdf-preview-modal-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .pdf-preview-scroll-container::-webkit-scrollbar {
            width: 12px;
        }
        .pdf-preview-scroll-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 6px;
        }
        .pdf-preview-scroll-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 6px;
        }
        .pdf-preview-scroll-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .pdf-preview-container {
            margin: 0;
            max-width: none;
        }
        .pdf-preview-wrapper {
            background: #fff;
            box-shadow: 0 0 12px 2px rgba(0,0,0,0.10);
            padding: 72pt;
            margin: 0 auto 40px auto;
            width: 816px;
            height: auto;
            min-height: 1056px;
            border-radius: 4px;
            position: relative;
            page-break-after: always;
            overflow: visible;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .pdf-preview-wrapper del,
        .pdf-preview-wrapper ins {
            word-wrap: break-word;
            overflow-wrap: break-word;
            display: inline;
            white-space: normal;
        }
        .pdf-preview-wrapper .cpp-template {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .pdf-preview-wrapper:last-child {
            page-break-after: avoid;
            margin-bottom: 0
        }
        .pdf-preview-page-number {
            position: absolute;
            bottom: 36pt;
            right: 72pt;
            font-size: 10pt;
            color: #666;
        }
        .pdf-preview-content {
            height: calc(100% - 50px);
            overflow: hidden;
        }';
    } else {
        $preview_css = '
        .pdf-preview-wrapper {
            background: none;
            box-shadow: none;
            padding: 0;
            margin: 0;
            max-width: none;
            min-width: 0;
        }';
    }

    $html = '
    <style>
    @page { margin: 72pt; }
    ' . $preview_css . '
    .pdf-preview-wrapper {
        font-family: "Times New Roman", Times, serif;
        font-size: 12pt;
        line-height: 1.5;
        color: #000;
    }
    .pdf-preview-wrapper h1,
    .pdf-preview-wrapper h2,
    .pdf-preview-wrapper h3 {
        font-family: "Times New Roman", Times, serif;
        font-weight: bold;
        text-align: center;
        margin-top: 24pt;
        margin-bottom: 12pt;
    }
    .pdf-preview-wrapper h1 { font-size: 18pt; text-transform: uppercase; }
    .pdf-preview-wrapper h2 { font-size: 16pt; }
    .pdf-preview-wrapper h3 { font-size: 14pt; }
    .pdf-preview-wrapper p { margin: 0 0 12pt 0; }
    .pdf-preview-wrapper .intro-page { page-break-after: always; margin-bottom: 120pt; }
    .pdf-preview-wrapper .intro-page div { margin-top: 12pt; }
    .pdf-preview-wrapper .footer-area { margin-top: 40pt; text-align: center; font-size: 11pt; color: #333; }
    </style>
    ';


    if ($is_preview) {
        $html .= '<div class="pdf-preview-scroll-container">
            <button class="pdf-preview-expand-btn" onclick="expandPdfPreview()" title="Expand to fullscreen">⛶ Expand</button>
            <div class="pdf-preview-container" id="pdf-preview-container">';
        // Create intro page (page 1)
        $intro_content = cpp_build_intro_header($company_name, $effective_date, $plan_options_selected);
        $html .= '<div class="pdf-preview-wrapper" data-page="1">';
        $html .= '<div class="pdf-preview-content intro-page">' . $intro_content . '</div>';
        $html .= '<div class="pdf-preview-page-number">Page 1</div>';
        $html .= '</div>';

        // Generate main content
        $main_content = '';
        $blocks = $template_data[$version]['components'] ?? [];
        $old_blocks = $old_version ? ($template_data[$old_version]['components'] ?? []) : [];

        foreach ($plan_options_selected as $option) {
            $option = trim($option);
            if (!$redline) {
                if (isset($blocks[$option])) {
                    $main_content .= $blocks[$option];
                }
            } else {
                $old = isset($old_blocks[$option]) ? $old_blocks[$option] : '';
                $new = isset($blocks[$option]) ? $blocks[$option] : '';
                $main_content .= cpp_redline_template_regions_dmp($old, $new);
            }
        }

        $main_content .= '<p style="text-align:right; font-size:10pt;"><em>Template Version: ' . esc_html($version) . '</em></p>';
        $main_content .= '<div class="footer-area"><p>&copy; ' . date('Y') . '  Kinney Law & Compliance. All rights reserved.</p></div>';

        // Store main content in a hidden div for pagination processing
        $html .= '<div id="main-content-source" style="display: none;">' . $main_content . '</div>';

        $html .= '</div></div>'; // Close pdf-preview-container and scroll container

        // Add modal overlay
        $html .= '
        <div class="pdf-preview-modal-overlay" id="pdf-preview-modal">
            <div class="pdf-preview-modal-container">
                <div class="pdf-preview-modal-header">
                    <div class="pdf-preview-modal-title">PDF Preview - Fullscreen</div>
                    <button class="pdf-preview-close-btn" onclick="closePdfPreview()" title="Close fullscreen">&times;</button>
                </div>
                <div class="pdf-preview-modal-content" id="pdf-preview-modal-content">
                    <!-- Content will be cloned here -->
                </div>
            </div>
        </div>';

        // Add pagination and modal scripts
        $html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            setTimeout(paginatePreview, 100); // Small delay to ensure content is rendered
        });

        function expandPdfPreview() {
            const modal = document.getElementById("pdf-preview-modal");
            const modalContent = document.getElementById("pdf-preview-modal-content");
            const originalContainer = document.getElementById("pdf-preview-container");
            
            // Clone the content to the modal
            modalContent.innerHTML = originalContainer.innerHTML;
            
            // Show the modal
            modal.classList.add("active");
            
            // Prevent body scrolling
            document.body.style.overflow = "hidden";
        }

        function closePdfPreview() {
            const modal = document.getElementById("pdf-preview-modal");
            
            // Hide the modal
            modal.classList.remove("active");
            
            // Restore body scrolling
            document.body.style.overflow = "";
        }

        // Close modal when clicking outside the container
        document.addEventListener("click", function(event) {
            const modal = document.getElementById("pdf-preview-modal");
            const modalContainer = modal.querySelector(".pdf-preview-modal-container");
            
            if (event.target === modal) {
                closePdfPreview();
            }
        });

        // Close modal with Escape key
        document.addEventListener("keydown", function(event) {
            if (event.key === "Escape") {
                closePdfPreview();
            }
        });

        function paginatePreview() {
            const container = document.getElementById("pdf-preview-container");
            const mainContentSource = document.getElementById("main-content-source");

            if (!mainContentSource || !container) return;

            const mainContentHtml = mainContentSource.innerHTML;
            mainContentSource.remove(); // Remove the hidden div

            // Create a temporary measuring container
            const tempContainer = document.createElement("div");
            tempContainer.style.position = "absolute";
            tempContainer.style.left = "-9999px";
            tempContainer.style.width = "672px"; // 816px - 144px (72pt padding on each side)
            tempContainer.style.fontSize = "12pt";
            tempContainer.style.lineHeight = "1.5";
            tempContainer.style.fontFamily = "Times New Roman, Times, serif";
            tempContainer.innerHTML = mainContentHtml;
            document.body.appendChild(tempContainer);

            const pageHeight = 1056;
            const padding = 144; // 72pt top + 72pt bottom in pixels
            const pageNumberHeight = 50; // Space for page number
            const availableHeight = pageHeight - padding - pageNumberHeight;

            let currentPage = 2; // Start from page 2 since intro is page 1
            let pages = [];
            let currentPageContent = document.createElement("div");
            let currentHeight = 0;

            // Process all child nodes
            const nodes = Array.from(tempContainer.childNodes);

            for (let i = 0; i < nodes.length; i++) {
                const node = nodes[i].cloneNode(true);

                // Create test container to measure this element
                const testContainer = document.createElement("div");
                testContainer.style.position = "absolute";
                testContainer.style.left = "-9999px";
                testContainer.style.width = "672px";
                testContainer.style.fontSize = "12pt";
                testContainer.style.lineHeight = "1.5";
                testContainer.style.fontFamily = "Times New Roman, Times, serif";
                testContainer.appendChild(node.cloneNode(true));
                document.body.appendChild(testContainer);

                const elementHeight = testContainer.offsetHeight;
                document.body.removeChild(testContainer);

                // Check if adding this element would exceed page height
                if (currentHeight + elementHeight > availableHeight && currentPageContent.children.length > 0) {
                    // Save current page
                    pages.push({
                        content: currentPageContent.innerHTML,
                        pageNumber: currentPage
                    });

                    // Start new page
                    currentPage++;
                    currentPageContent = document.createElement("div");
                    currentHeight = 0;
                }

                // Add element to current page
                currentPageContent.appendChild(node);
                currentHeight += elementHeight;

                // Handle very tall elements that might need to be split
                if (elementHeight > availableHeight) {
                    // For very tall elements, we still add them but they will overflow
                    // This maintains content integrity rather than losing content
                    console.warn("Element exceeds page height and may overflow:", node);
                }
            }

            // Add the last page if it has content
            if (currentPageContent.children.length > 0) {
                pages.push({
                    content: currentPageContent.innerHTML,
                    pageNumber: currentPage
                });
            }

            // Clean up temporary container
            document.body.removeChild(tempContainer);

            // Add paginated main content pages to the preview container
            pages.forEach(page => {
                const pageDiv = document.createElement("div");
                pageDiv.className = "pdf-preview-wrapper";
                pageDiv.setAttribute("data-page", page.pageNumber);
                pageDiv.innerHTML = `
                    <div class="pdf-preview-content">${page.content}</div>
                    <div class="pdf-preview-page-number">Page ${page.pageNumber}</div>
                `;
                container.appendChild(pageDiv);
            });
        }
        </script>';

    } else {
        $html .= '<div class="pdf-preview-wrapper">';
        // Cover page
        $html .= '<div class="intro-page">' . cpp_build_intro_header($company_name, $effective_date, $plan_options_selected) . '</div>';

        $blocks = $template_data[$version]['components'] ?? [];
        $old_blocks = $old_version ? ($template_data[$old_version]['components'] ?? []) : [];

        // Main content
        foreach ($plan_options_selected as $option) {
            $option = trim($option);
            if (!$redline) {
                if (isset($blocks[$option])) {
                    $html .= $blocks[$option];
                }
            } else {
                $old = isset($old_blocks[$option]) ? $old_blocks[$option] : '';
                $new = isset($blocks[$option]) ? $blocks[$option] : '';
                $html .= cpp_redline_template_regions_dmp($old, $new);
            }
        }

        $html .= '<p style="text-align:right; font-size:10pt;"><em>Template Version: ' . esc_html($version) . '</em></p>';
        $html .= '<div class="footer-area"><p>&copy; ' . date('Y') . '  Kinney Law & Compliance. All rights reserved.</p></div>';

        $html .= '</div>'; // Close pdf-preview-wrapper
    }
    // Always replace tokens last
    $html = cpp_replace_tokens($html, $plan_id);

    return $html;
}