@push('crud_fields_styles')
<style>
  .bp-reviews { width: 100%; }
  .bp-reviews .review-item {
    border-left: 2px solid #eee;
    margin-left: 10px;
    padding-left: 10px;
    margin-bottom: 1.25rem;
  }
  .bp-reviews .review-header {
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }
  .bp-reviews .review-avatar-wrapper {
    width: 44px;
    flex-shrink: 0;
  }
  .bp-reviews .review-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
  }
  .bp-reviews .review-avatar--placeholder {
    background: #e9ecef;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
    text-transform: uppercase;
  }
  .bp-reviews .review-actions .btn { margin-right: 6px; margin-top: 4px; }
  .bp-reviews .review-meta-line {
    color:#777;
    font-size: 13px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 6px;
  }
  .bp-reviews .review-owner {
    font-weight: 600;
    font-size: 14px;
  }
  .bp-reviews .review-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 999px;
    background-color: #eaf7ed;
    color: #1f7a36;
    margin-left: 6px;
  }
  .bp-reviews .review-pros,
  .bp-reviews .review-cons {
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 13px;
    margin-top: 6px;
  }
  .bp-reviews .review-cons {
    background: #fff5f5;
  }
  .bp-reviews .review-pros strong,
  .bp-reviews .review-cons strong {
    display: block;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 4px;
    color: #6c757d;
  }
  .bp-reviews .review-text {
    white-space: pre-wrap;
  }
  .bp-reviews .js-owner-guest.d-none {
    display: none !important;
  }
  .bp-reviews .select2-container--bootstrap {
    width: 100% !important;
  }
</style>
@endpush
