# User Guide: Hybrid Campaigns

## Overview

Hybrid Campaigns allow you to create WhatsApp campaigns in two ways:
- **Template-based**: Use pre-approved WhatsApp templates for business messaging
- **Direct Message**: Create custom messages without template approval

## Getting Started

### Accessing Campaign Creation

1. Navigate to **Campaigns** in the left sidebar
2. Click **Create Campaign** button
3. You'll see the hybrid campaign creation form

## Campaign Types

### 1. Template-Based Campaigns

Best for:
- Business communications requiring compliance
- Recurring message patterns
- Professional branded messaging
- High-volume messaging

#### How to Create Template Campaign:

1. **Select Campaign Type**: Choose "Template" from the dropdown
2. **Select Template**: Browse and select from your approved templates
3. **Configure Provider**:
   - WhatsApp Web JS (recommended for flexibility)
   - Meta Business API (for official Business messaging)
4. **Select Contacts**: Choose contact group or "All Contacts"
5. **Set Schedule**: Send immediately or schedule for later
6. **Preview**: Review your message preview on the right
7. **Create Campaign**: Click submit to create your campaign

#### Template Features:
- ✅ Pre-approved content ensures compliance
- ✅ Professional formatting and branding
- ✅ Variable personalization (`{{first_name}}`, `{{company}}`, etc.)
- ✅ Media support (images, videos, documents)
- ✅ Interactive buttons (reply, URL, phone)
- ✅ Higher message throughput

### 2. Direct Message Campaigns

Best for:
- Quick notifications and announcements
- Personal messages and greetings
- Emergency communications
- One-time campaigns

#### How to Create Direct Campaign:

1. **Select Campaign Type**: Choose "Direct Message" from the dropdown
2. **Configure Header**:
   - **Text Header**: Add a title (max 60 characters)
   - **Media Header**: Upload image, video, or document
3. **Write Message Body**: Compose your message (max 1024 characters)
4. **Add Footer** (Optional): Add a short footer text (max 60 characters)
5. **Configure Buttons** (Optional):
   - **Reply Buttons**: Quick response options (max 3)
   - **Action Buttons**: URL or phone number buttons
6. **Select Provider**: Choose WhatsApp Web JS or Meta API
7. **Select Contacts**: Choose your target audience
8. **Schedule Campaign**: Set delivery timing
9. **Preview Message**: See real-time preview on the right
10. **Create Campaign**: Launch your campaign

#### Direct Message Features:
- ✅ Instant message creation without templates
- ✅ Full creative control over content
- ✅ Media file upload support
- ✅ Interactive button options
- ✅ Variable personalization support
- ✅ Immediate campaign activation

## Provider Selection

### WhatsApp Web JS (Recommended)

**Advantages:**
- Faster setup and activation
- No template approval required for direct messages
- More flexible content creation
- Better for testing and small campaigns
- Lower cost for entry-level usage

**Limitations:**
- Lower message throughput
- May require account management
- Higher delivery variability

### Meta Business API

**Advantages:**
- Official WhatsApp Business integration
- Higher message reliability
- Better delivery tracking
- Higher throughput limits
- Professional compliance

**Requirements:**
- Approved business verification
- Template approval for structured messages
- Official WhatsApp Business account

## Message Personalization

### Available Variables

| Variable | Description | Example Usage |
|----------|-------------|---------------|
| `{{first_name}}` | Contact's first name | "Hello {{first_name}}" |
| `{{last_name}}` | Contact's last name | "Dear {{last_name}}" |
| `{{full_name}}` | Contact's full name | "Hi {{full_name}}" |
| `{{email}}` | Contact's email address | "Check {{email}}" |
| `{{phone}}` | Contact's phone number | "Call {{phone}}" |
| `{{company}}` | Contact's company | "From {{company}}" |
| `{{position}}` | Contact's job title | "Dear {{position}}" |

### Tips for Personalization

1. **Use meaningful variables**: Select variables that enhance your message
2. **Test with sample data**: Preview your message before sending
3. **Handle missing data**: Provide fallback text for empty fields
4. **Keep it natural**: Use personalization that feels authentic

## Media Guidelines

### Supported Media Types

| Media Type | Max Size | Supported Formats | Recommendations |
|------------|----------|-------------------|----------------|
| **Images** | 5MB | JPEG, JPG, PNG, GIF | Use high-quality, optimized images |
| **Videos** | 16MB | MP4, MOV, AVI | Keep videos short and engaging |
| **Documents** | 100MB | PDF, DOC, DOCX, XLS, PPT | Use readable, well-formatted documents |

### Best Practices

1. **Optimize file sizes**: Compress images and videos for faster delivery
2. **Use appropriate formats**: Follow WhatsApp's recommended formats
3. **Test media uploads**: Verify files work before campaign launch
4. **Consider mobile viewing**: Ensure media looks good on mobile devices

## Button Configuration

### Reply Buttons
- **Purpose**: Quick response options for recipients
- **Limit**: Maximum 3 buttons per message
- **Best for**: Surveys, confirmations, choices

**Example:**
```
Button 1: "Yes, I'm interested"
Button 2: "Not right now"
Button 3: "Tell me more"
```

### Action Buttons
- **URL Buttons**: Link to websites or landing pages
- **Phone Buttons**: Call business numbers directly
- **Limit**: Maximum 2 action buttons per message

**Example:**
```
Button 1: "Visit Website" → https://example.com
Button 2: "Call Us" → +1234567890
```

## Campaign Scheduling

### Immediate Sending
- **When**: Messages sent as soon as campaign is created
- **Best for**: Time-sensitive communications
- **Processing**: Queued immediately in the message system

### Scheduled Sending
- **When**: Messages sent at specified future time
- **Best for**: Regular communications, time-zone coordination
- **Scheduling**: Can be scheduled up to 30 days in advance

### Scheduling Tips
1. **Consider time zones**: Schedule based on recipient locations
2. **Avoid peak hours**: Consider optimal delivery times
3. **Batch large campaigns**: For large groups, consider staggered delivery
4. **Test scheduling**: Verify timing with test campaigns first

## Campaign Management

### View Campaign Status

1. Go to **Campaigns** page
2. View your campaign list with status indicators
3. Click on any campaign to see detailed statistics

### Campaign Statuses

| Status | Description | Actions Available |
|--------|-------------|-------------------|
| **Pending** | Campaign created, waiting to start | Edit, Cancel |
| **Scheduled** | Campaign scheduled for future delivery | Edit, Cancel |
| **Ongoing** | Campaign currently sending | Pause, Stop |
| **Completed** | Campaign finished successfully | View Results, Duplicate |
| **Failed** | Campaign encountered errors | Retry, View Logs |

### Performance Metrics

Track these key metrics for campaign success:
- **Delivery Rate**: Percentage of messages successfully delivered
- **Read Rate**: Percentage of messages read by recipients
- **Reply Rate**: Percentage of responses received
- **Click Rate**: For URL buttons, percentage of clicks

## Troubleshooting

### Common Issues

#### Campaign Creation Failed
- **Cause**: Validation errors or missing data
- **Solution**: Check all required fields and ensure proper formatting

#### Message Delivery Failed
- **Cause**: WhatsApp account issues or provider problems
- **Solution**: Check WhatsApp account status and try again

#### Media Upload Failed
- **Cause**: File size or format issues
- **Solution**: Verify file meets size and format requirements

#### Provider Not Available
- **Cause**: WhatsApp Web JS session disconnected
- **Solution**: Reconnect WhatsApp account or use Meta API

### Getting Help

1. **Check Documentation**: Review this guide and technical documentation
2. **Contact Support**: Reach out through the support channel
3. **Review Logs**: Check campaign logs for specific error details
4. **Test Again**: Try with smaller test campaigns first

## Best Practices

### Before Creating Campaigns

1. **Verify Contact Data**: Ensure your contact list is clean and accurate
2. **Test Message Content**: Send test messages to verify content
3. **Check WhatsApp accounts**: Ensure WhatsApp connections are active
4. **Review Compliance**: Follow WhatsApp Business messaging guidelines

### During Campaign Creation

1. **Use Clear Messages**: Write concise, engaging content
2. **Personalize Appropriately**: Use variables meaningfully
3. **Test Preview**: Always review message preview before sending
4. **Consider Timing**: Choose optimal delivery times

### After Campaign Launch

1. **Monitor Performance**: Track delivery and engagement metrics
2. **Respond Quickly**: Handle replies and inquiries promptly
3. **Learn Results**: Analyze campaign performance for future improvements
4. **Maintain Sessions**: Keep WhatsApp accounts active for reliability

## Compliance Guidelines

### WhatsApp Business Rules

1. **Opt-in Required**: Only message users who have opted in
2. **Message Content**: Follow WhatsApp's content policies
3. **Rate Limits**: Respect WhatsApp's rate limiting guidelines
4. **Data Privacy**: Protect contact information and privacy

### Best Practices

- Obtain explicit consent before messaging
- Provide opt-out options in your messages
- Respect message frequency limits
- Keep message content professional and appropriate
- Follow local regulations and laws

## Advanced Features

### A/B Testing
- Create multiple message variations
- Test different content approaches
- Compare performance metrics

### Automation
- Set up recurring campaign schedules
- Integrate with other business systems
- Use webhooks for real-time updates

### Analytics
- Track detailed engagement metrics
- Export campaign data for analysis
- Generate performance reports

---

## Quick Reference

### Campaign Creation Checklist

- [ ] Choose campaign type (Template or Direct)
- [ ] Select or create message content
- [ ] Add personalization variables
- [ ] Configure buttons (if needed)
- [ ] Select provider (WebJS or Meta API)
- [ ] Choose target contacts
- [ ] Set delivery schedule
- [ ] Review message preview
- [ ] Create and launch campaign

### Emergency Contacts

- **Technical Support**: [Support Email/Phone]
- **Documentation**: [Documentation Link]
- **Community Forum**: [Forum Link]

---

*Last Updated: 2025-11-14*
*Version: 1.0*