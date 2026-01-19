# SureCart Shippo Integration

A comprehensive WordPress plugin that integrates SureCart with Shippo to provide seamless shipping functionality including address validation, live shipping rates, and label printing.

## Features

### Core Functionality
- **Address Validation**: Real-time address validation at checkout using Shippo's validation API
- **Live Shipping Rates**: Dynamic shipping rate calculation for US and international destinations
- **Automated Packaging**: Intelligent packaging estimation based on product dimensions and weight
- **Label Printing**: One-click label purchase with 4×6 PDF label generation
- **Rate Persistence**: Selected shipping rates saved to SureCart orders
- **Multi-Carrier Support**: Support for USPS, FedEx, UPS, DHL, and other Shippo carriers

### Admin Features
- **Settings Interface**: Comprehensive settings UI with multiple tabs
- **Box Catalog**: CRUD interface for managing shipping boxes
- **Order Panel**: Integrated order panel for label purchase and tracking
- **Product Metadata**: Product-level shipping metadata (weight, dimensions, profiles)
- **Diagnostics**: Built-in logging and debugging tools
- **Test Mode**: Separate test and live API token support

### Security & Reliability
- **Encrypted Storage**: API tokens encrypted at rest using WordPress salts
- **Idempotency**: Prevents duplicate label purchases on double-clicks
- **Rate Caching**: Reduces API calls with configurable TTL caching
- **Fallback Shipping**: Configurable fallback when Shippo is unavailable
- **Permission Checks**: Proper capability checks and nonces throughout

## Requirements

- WordPress 5.9 or higher
- PHP 7.4 or higher
- SureCart plugin installed and activated
- Shippo account (test and/or live)

## Installation

1. **Upload Plugin**
   ```bash
   cd wp-content/plugins
   git clone <repository-url> surecart-shippo
   ```

2. **Activate Plugin**
   - Go to WordPress Admin > Plugins
   - Find "SureCart Shippo Integration"
   - Click "Activate"

3. **Configure Settings**
   - Go to WordPress Admin > Shippo Settings
   - Enter your Shippo API tokens
   - Configure origin address
   - Set up packaging constants
   - Add boxes to the catalog

## Configuration

### Shippo API Setup

1. Create a Shippo account at [https://goshippo.com](https://goshippo.com)
2. Get your API tokens from the Shippo dashboard
3. In WordPress Admin > Shippo Settings > Shippo API tab:
   - Select "Test" or "Live" mode
   - Enter your API token
   - Click "Test Connection" to verify

### Origin Address

Configure your ship-from address in the "Origin Address" tab. This is required for rate calculation.

### Box Catalog

Add your shipping boxes in the "Box Catalog" tab:
- Box ID (unique identifier)
- Internal dimensions (length × width × height in inches)
- Empty weight (box weight in pounds)
- Maximum weight capacity
- Box type (RSC, mailer, tube, etc.)

**Tip**: Use the "Seed Default Boxes" button to create common USPS flat rate boxes.

### Product Setup

For each product, configure shipping metadata:
1. Edit product in WordPress Admin
2. Find "Shipping (Shippo)" meta box
3. Enter:
   - Weight (lb)
   - Dimensions (L × W × H in inches)
   - Shipping profile (Small/Medium/Large, Rugged/Fragile)
   - Special flags (ship alone, fragile, irregular shape)

### Packaging Profiles

The plugin uses predefined profiles with specific padding and pack factors:

- **Small Rugged/Fragile**: 0.5" padding
- **Medium Rugged/Fragile**: 0.75" padding
- **Large Rugged/Fragile**: 1.0" padding
- **Long Item/Irregular**: 1.25" padding

Pack factors:
- Rugged: 1.15×
- Fragile: 1.25×
- Irregular/Long: 1.30×

## Usage

### For Customers (Checkout)

1. Customer adds products to cart
2. Customer enters shipping address
3. Address is validated via Shippo
4. Live shipping rates are displayed
5. Customer selects preferred shipping method
6. Selected rate is saved to order

### For Fulfillment (Admin)

1. Navigate to order in WordPress Admin
2. Find "Shipping (Shippo)" panel
3. Review selected service and packaging
4. Click "Buy/Print Label"
5. Label is purchased from Shippo
6. Click "Open 4×6 PDF Label" to print
7. Tracking number is automatically saved

## Architecture

### Directory Structure

```
surecart-shippo/
├── surecart-shippo.php         # Main plugin file
├── includes/
│   ├── Autoloader.php          # PSR-4 autoloader
│   ├── Core/
│   │   ├── ShippoClient.php    # Shippo API client
│   │   └── Encryption.php      # Token encryption
│   ├── Packaging/
│   │   ├── PackagingEngine.php # Packaging estimation
│   │   └── BoxCatalog.php      # Box management
│   ├── Services/
│   │   ├── RateService.php     # Rate calculation
│   │   └── LabelService.php    # Label purchase
│   ├── Admin/
│   │   ├── SettingsPage.php    # Settings UI
│   │   ├── OrderPanel.php      # Order label UI
│   │   └── ProductMetaBox.php  # Product metadata
│   ├── Frontend/
│   │   ├── AddressValidation.php
│   │   └── ShippingRates.php
│   ├── Integration/
│   │   └── SureCartIntegration.php
│   └── Diagnostics/
│       └── Logger.php          # Logging system
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       ├── admin.js
│       ├── order-panel.js
│       └── address-validation.js
└── README.md
```

### Key Classes

- **ShippoClient**: Handles all Shippo API communication
- **PackagingEngine**: Estimates packaging based on cart items
- **RateService**: Manages rate calculation and caching
- **LabelService**: Handles label purchase with idempotency
- **BoxCatalog**: Manages box database CRUD operations

### Data Flow

1. **Checkout Flow**:
   ```
   Cart → PackagingEngine → ShippoClient → Rates → Cache → Display
   ```

2. **Fulfillment Flow**:
   ```
   Order → Verify → LabelService → ShippoClient → Label PDF
   ```

## Packaging Algorithm

The packaging engine uses a sophisticated algorithm:

1. **Separate Special Items**: Items marked "ship alone" go in individual parcels
2. **Calculate Buffered Dimensions**: Add padding based on shipping profile
3. **Apply Pack Factor**: Multiply by profile-specific factor (rugged/fragile/irregular)
4. **Check Weight Threshold**: Split if over 40 lb (configurable)
5. **Estimate Box Volume**: Calculate required volume with cart-level factor
6. **Find Fitting Box**: Match to smallest box that fits dimensions and weight
7. **Greedy Split**: If no box fits, split items and repeat

## API Integration

### Shippo API Endpoints Used

- `POST /addresses/` - Address validation
- `POST /shipments/` - Create shipment and get rates
- `GET /shipments/{id}` - Retrieve shipment
- `POST /transactions/` - Purchase label
- `GET /transactions/{id}` - Get transaction details
- `POST /transactions/{id}/refund` - Refund label

### SureCart Integration Points

- `surecart/shipping/methods` - Register shipping method
- `surecart/shipping/calculate_rates` - Provide live rates
- `surecart/checkout/validate_address` - Validate addresses
- `surecart/checkout/order_created` - Save selected rate
- `surecart/order/paid` - Enable label purchase

## Caching Strategy

Rates are cached using WordPress transients:
- **Cache Key**: MD5 hash of cart items + destination
- **TTL**: 10 minutes (configurable)
- **Invalidation**: Automatic cleanup via cron

## Security

### Best Practices Implemented

- API tokens encrypted with AES-256-CBC using WordPress salts
- All AJAX actions require nonces
- Capability checks before admin operations
- SQL prepared statements for database queries
- Input sanitization and output escaping
- Idempotency locks to prevent race conditions

## Troubleshooting

### Common Issues

**"Shippo API token is not configured"**
- Ensure you've entered your API token in settings
- Check that you've selected the correct mode (test/live)

**"Ship-from address is not fully configured"**
- Complete all required fields in Origin Address tab
- Minimum required: Street, City, ZIP

**"No shipping rates available"**
- Check that products have weight and dimensions
- Verify Shippo API token is valid
- Enable diagnostics and check logs

**"No box found for dimensions"**
- Add larger boxes to catalog
- Check product dimensions are accurate
- Consider enabling item splitting

### Debug Mode

Enable verbose logging:
1. Go to Shippo Settings > Diagnostics
2. Set logging level to "Debug" or "All"
3. View logs in the diagnostics tab
4. Check error messages and context

### Clear Cache

If rates seem stale:
1. Go to Shippo Settings > Diagnostics
2. Click "Clear Cache"
3. Rates will be recalculated on next request

## Development

### Coding Standards

- Follows PSR-12 coding standard
- WordPress coding standards for hooks/filters
- PHPDoc blocks for all classes and methods
- Namespaced with `SureCartShippo\`

### Extending the Plugin

#### Add Custom Packaging Logic

```php
add_filter('surecart_shippo/packaging/estimate', function($estimate, $cart_items) {
    // Custom packaging logic
    return $estimate;
}, 10, 2);
```

#### Modify Rate Display

```php
add_filter('surecart_shippo/rates/format_label', function($label, $rate) {
    // Custom label formatting
    return $label;
}, 10, 2);
```

#### Hook into Label Purchase

```php
add_action('surecart_shippo/label/purchased', function($order_id, $transaction) {
    // Custom post-purchase logic
}, 10, 2);
```

## Roadmap

### Future Enhancements

- [ ] Direct USB thermal printer support
- [ ] Advanced 3D bin packing algorithm
- [ ] Automated customs form generation
- [ ] Insurance and signature options
- [ ] Multi-warehouse support
- [ ] Batch label printing
- [ ] Shipment tracking webhooks
- [ ] Return label generation

## Support

For issues, feature requests, or questions:

- **GitHub Issues**: [Repository Issues](https://github.com/yourusername/surecart-shipping/issues)
- **Documentation**: See CLAUDE.md for developer documentation
- **Shippo Docs**: [https://docs.goshippo.com](https://docs.goshippo.com)
- **SureCart Docs**: [https://surecart.com/docs](https://surecart.com/docs)

## License

GPL v2 or later

## Credits

Developed for seamless integration between SureCart and Shippo shipping services.

## Changelog

### 1.0.0 (2026-01-19)
- Initial release
- Address validation
- Live shipping rates
- Packaging estimation engine
- Label printing
- Box catalog
- Admin settings interface
- Diagnostics and logging
