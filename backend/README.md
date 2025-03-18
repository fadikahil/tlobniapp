# Item Creation Example

## Example Request for Experience Item

```json
{
  "name": "Dsad",
  "description": "Dadsa",
  "price": "44",
  "contact": "123123",
  "video_link": "",
  "category_id": "2",
  "post_type": "PostType.experience",
  "price_type": "consultation",
  "special_tags": {
    "exclusive_women": true,
    "corporate_package": false
  },
  "location_type": "online",
  "expiration_date": "2025-03-26T00:00:00.000",
  "expiration_time": "5:12"
}
```

## Required Fields
- name
- category_id
- price

## Optional Fields
- description
- address
- contact
- show_only_to_premium
- video_link
- image (file upload)
- gallery_images (array of file uploads)
- country
- state
- city
- custom_fields (JSON)
- custom_field_files (array of file uploads)
- slug
- post_type (use "PostType.experience" for experience items, otherwise service)
- price_type
- special_tags (JSON object with boolean values)
- location_type
- expiration_date (required for experience items)
- expiration_time (required for experience items)

## Notes
- When post_type is "PostType.experience", the item will expire at the specified expiration_date and expiration_time
- When post_type is not "PostType.experience", the item will expire based on the package end date 