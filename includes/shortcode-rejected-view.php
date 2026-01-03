<div class="taa-staging-wrapper">
    <div class="taa-staging-header">
        <h2 style="color:#d9534f;">Rejected Trades History</h2>
    </div>
    <div class="taa-table-responsive">
        <table class="taa-staging-table">
            <thead><tr><th>Date</th><th>Dir</th><th>Instrument</th><th>Strike</th><th>Entry</th><th>Target</th><th>SL</th><th>Chart</th></tr></thead>
            <tbody>
                <?php foreach($rows as $r): ?>
                <tr>
                    <td><?php echo date('d M H:i', strtotime($r->created_at)); ?></td>
                    <td><span class="taa-badge <?php echo $r->dir=='BUY'?'taa-buy':'taa-sell'; ?>"><?php echo $r->dir; ?></span></td>
                    <td style="font-weight:600;"><?php echo esc_html($r->chart_name); ?></td>
                    <td><?php echo esc_html($r->strike); ?></td>
                    <td><?php echo number_format($r->entry); ?></td>
                    <td><?php echo number_format($r->target); ?></td>
                    <td><?php echo number_format($r->sl); ?></td>
                    <td><?php if($r->image_url): ?><a href="<?php echo esc_url($r->image_url); ?>" target="_blank" class="taa-btn-view">View</a><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>