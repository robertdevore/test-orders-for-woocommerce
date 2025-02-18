jQuery(document).ready(function ($) {
    let offset = 0;
    let totalDeleted = 0;
    let totalScanned = 0;

    $('#delete-test-orders-btn').on('click', function () {
        const $button = $(this);
        const $progressBar = $('#test-orders-progress-bar');
        const $progressText = $('#test-orders-progress-text');
        const $progressContainer = $('#test-orders-progress');
        const $statusMessage = $('#test-orders-status-message');

        $button.prop('disabled', true);
        $progressContainer.show();
        $statusMessage.text('');
        $progressBar.css('width', '0%');
        $progressText.text('Progress: 0%');

        function deleteBatch() {
            $.post(wcTestOrders.ajax_url, {
                action: 'wc_test_orders_delete_test_orders',
                nonce: wcTestOrders.nonce,
                offset: offset,
                total_deleted: totalDeleted,
                total_scanned: totalScanned,
            })
                .done(function (response) {
                    if (response.success) {
                        const data = response.data;

                        if (data.total_scanned === 0) {
                            $progressText.text('No test orders found.');
                            $statusMessage.text('No test orders found.');
                            $button.prop('disabled', false);
                            return;
                        }

                        if (totalScanned === 0) {
                            totalScanned = data.total_scanned;
                        }

                        totalDeleted = data.total_deleted;
                        offset = data.next_offset;

                        const percentage = data.progress_percentage;
                        $progressBar.css('width', `${percentage}%`);
                        $progressText.text(
                            `Progress: ${percentage}% (${totalDeleted} orders deleted out of ${totalScanned} scanned)`
                        );

                        if (data.has_more) {
                            deleteBatch();
                        } else {
                            $progressBar.css('width', '100%');
                            $progressText.text(
                                `Progress: 100% (${totalDeleted} orders deleted out of ${totalScanned} scanned)`
                            );
                            $statusMessage.text('All test orders have been deleted successfully!');
                            $button.prop('disabled', false);
                        }
                    } else {
                        handleError('An error occurred. Please try again.');
                    }
                })
                .fail(function (xhr, status, error) {
                    console.error('AJAX Request Failed:', status, error);
                    handleError('AJAX request failed. Check the console for details.');
                });
        }

        function handleError(message) {
            $statusMessage.text(message);
            $button.prop('disabled', false);
        }

        deleteBatch();
    });
});
