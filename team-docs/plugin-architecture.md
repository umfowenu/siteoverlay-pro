# Plugin Team Internal Documentation

## License Enforcement Logic
- Plugin checks license status via Railway API
- Unlicensed state: All features disabled except basic UI
- Trial state: Full features enabled until expiry
- Licensed state: All features permanently enabled

## API Integration Points
- /validate-license: Check current license status
- /request-trial: Register new trial license
- Field mapping: plugin 'name' → API 'name' (not 'full_name')

## WordPress Integration
- Hooks: wp_head, wp_ajax_*, admin_menu
- Security: wp_nonce verification on all AJAX calls
- Performance: License check cached, overlay loads first

## Admin Interface States
- Unlicensed: "Plugin Inactive" + trial registration form
- Trial Active: License details + expiry countdown  
- Licensed: License details + renewal info
- Trial Expired: "Trial Ended" + upgrade prompts

## Development Notes
- Never slow overlay display with license checks
- Validate in background after overlay renders
- Maintain scrollbar fix and CSS optimizations 