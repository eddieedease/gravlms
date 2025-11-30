# GravLMS LTI Integration Guide

This comprehensive guide explains how to use LTI (Learning Tools Interoperability) with GravLMS in three different scenarios:

1. **Consumer Mode (LTI 1.1)**: Launch external LTI 1.1 tools from GravLMS
2. **Consumer Mode (LTI 1.3)**: Launch external LTI 1.3 tools from GravLMS
3. **Provider Mode**: Serve GravLMS courses to external LMS platforms

---

## Table of Contents

- [What is LTI?](#what-is-lti)
- [GravLMS LTI Endpoints](#gravlms-lti-endpoints)
- [Consumer Mode: LTI 1.1](#consumer-mode-lti-11)
- [Consumer Mode: LTI 1.3](#consumer-mode-lti-13)
- [Provider Mode: Serving GravLMS to External Platforms](#provider-mode-serving-gravlms-to-external-platforms)
- [Testing with Real Platforms](#testing-with-real-platforms)
- [Troubleshooting](#troubleshooting)

---

## What is LTI?

**LTI (Learning Tools Interoperability)** is a standard developed by IMS Global that allows learning management systems (LMS) to integrate with external tools and content providers securely.

### Key Concepts

- **Tool Consumer**: The LMS that launches external tools (e.g., GravLMS launching an external quiz tool)
- **Tool Provider**: The external tool being launched (e.g., GravLMS serving content to Canvas)
- **Launch**: The process of securely passing user and context information from consumer to provider
- **LTI 1.1**: Uses OAuth 1.0 for security (simpler, older standard)
- **LTI 1.3**: Uses OAuth 2.0 and OpenID Connect (more secure, modern standard)

---

## GravLMS LTI Endpoints

GravLMS provides the following LTI endpoints:

### Provider Mode Endpoints (When GravLMS is the Tool)

```
OIDC Login URL:  http://localhost:8080/api/lti/login
Launch URL:      http://localhost:8080/api/lti/launch
JWKS URL:        http://localhost:8080/api/lti/jwks
```

### Consumer Mode Endpoints (When GravLMS Launches External Tools)

Consumer mode uses the LTI tools configured in the Admin panel. Each tool has its own launch URL.

---

## Consumer Mode: LTI 1.1

In this mode, GravLMS acts as a **Tool Consumer** and launches external LTI 1.1 tools.

### Use Case

You want to integrate an external tool (e.g., a quiz platform, video player, or simulation) into your GravLMS courses.

### Setup Steps

#### 1. Register the External LTI 1.1 Tool

1. Log in to GravLMS as an admin
2. Navigate to the **Admin** panel
3. Go to the **LTI Tools** tab
4. Click **Add LTI Tool**
5. Fill in the following information:

   - **Name**: Friendly name for the tool (e.g., "External Quiz Tool")
   - **Tool URL**: The launch URL provided by the tool provider
   - **LTI Version**: Select `1.1`
   - **Consumer Key**: Provided by the tool provider
   - **Shared Secret**: Provided by the tool provider

6. Click **Save**

#### 2. Create an LTI Course

1. Navigate to the **Admin** panel
2. Go to the **Courses** tab
3. Click **Create Course**
4. Fill in the course details:
   - **Title**: Course name
   - **Description**: Course description
   - **External LTI Tool**: Check this box
   - **LTI Tool**: Select the tool you registered in step 1
   - **Custom Launch URL** (optional): Override the default tool URL for this specific course

5. Click **Save**

#### 3. Assign the Course to Users/Groups

1. In the **Admin** panel, go to **Users** or **Groups**
2. Select a user or group
3. Assign the LTI course you created

#### 4. Launch the Tool

1. Log in as a user who has been assigned the course
2. Go to the **Dashboard**
3. Click on the LTI course
4. GravLMS will automatically:
   - Generate OAuth 1.0 signature
   - Send user and context information to the external tool
   - Launch the tool in a new window or iframe

### LTI 1.1 Launch Parameters

GravLMS sends the following standard LTI 1.1 parameters:

```
lti_message_type: basic-lti-launch-request
lti_version: LTI-1p0
resource_link_id: [course_id]
user_id: [user_id]
roles: Learner
lis_person_name_full: [username]
context_id: gravlms
context_title: GravLMS
context_label: GravLMS
tool_consumer_instance_guid: gravlms-instance
tool_consumer_instance_name: GravLMS
```

### Example: Integrating with an External Quiz Tool

**Scenario**: You want to use an external quiz platform that supports LTI 1.1.

1. The quiz platform provides:
   - Launch URL: `https://quiztool.example.com/lti/launch`
   - Consumer Key: `gravlms_key`
   - Shared Secret: `secret123`

2. Register the tool in GravLMS Admin → LTI Tools
3. Create a course titled "Math Quiz" and select the quiz tool
4. Assign to students
5. Students click "Math Quiz" in their dashboard and are launched into the external quiz tool

---

## Consumer Mode: LTI 1.3

In this mode, GravLMS acts as a **Tool Consumer** and launches external LTI 1.3 tools using modern OAuth 2.0 and OpenID Connect.

### Use Case

You want to integrate a modern LTI 1.3 tool that requires enhanced security and supports advanced features like deep linking and assignment grading.

### Setup Steps

#### 1. Register the External LTI 1.3 Tool

1. Log in to GravLMS as an admin
2. Navigate to the **Admin** panel
3. Go to the **LTI Tools** tab
4. Click **Add LTI Tool**
5. Fill in the following information:

   - **Name**: Friendly name for the tool (e.g., "Modern Video Platform")
   - **Tool URL**: The launch URL provided by the tool provider
   - **LTI Version**: Select `1.3`
   - **Client ID**: Provided by the tool provider
   - **Initiate Login URL**: The OIDC login initiation URL
   - **Public Key**: The tool's public JWK (JSON Web Key)

6. Click **Save**

#### 2. Register GravLMS with the External Tool

The external tool provider will need the following information from GravLMS:

```
Platform Issuer:     http://localhost:8080
Authorization URL:   http://localhost:8080/api/lti/authorize
Token URL:           http://localhost:8080/api/lti/token
JWKS URL:            http://localhost:8080/api/lti/jwks
```

> **Note**: Replace `localhost:8080` with your actual domain in production.

#### 3. Create an LTI Course

Follow the same steps as LTI 1.1 (see above), but select an LTI 1.3 tool.

#### 4. Launch the Tool

1. Log in as a user who has been assigned the course
2. Go to the **Dashboard**
3. Click on the LTI course
4. GravLMS will automatically:
   - Initiate OIDC login flow
   - Generate and sign a JWT (JSON Web Token)
   - Send user and context claims to the external tool
   - Launch the tool securely

### LTI 1.3 Launch Claims

GravLMS sends the following LTI 1.3 claims in the JWT:

```json
{
  "iss": "http://localhost:8080",
  "aud": "[client_id]",
  "sub": "[user_id]",
  "exp": [expiration_timestamp],
  "iat": [issued_at_timestamp],
  "nonce": "[unique_nonce]",
  "https://purl.imsglobal.org/spec/lti/claim/message_type": "LtiResourceLinkRequest",
  "https://purl.imsglobal.org/spec/lti/claim/version": "1.3.0",
  "https://purl.imsglobal.org/spec/lti/claim/deployment_id": "1",
  "https://purl.imsglobal.org/spec/lti/claim/target_link_uri": "[tool_url]",
  "https://purl.imsglobal.org/spec/lti/claim/resource_link": {
    "id": "[course_id]",
    "title": "[course_title]"
  },
  "https://purl.imsglobal.org/spec/lti/claim/roles": [
    "http://purl.imsglobal.org/vocab/lis/v2/membership#Learner"
  ],
  "https://purl.imsglobal.org/spec/lti/claim/context": {
    "id": "gravlms",
    "label": "GravLMS",
    "title": "GravLMS"
  }
}
```

### Example: Integrating with a Modern LTI 1.3 Tool

**Scenario**: You want to use a modern video platform that supports LTI 1.3.

1. The video platform provides:
   - Launch URL: `https://videos.example.com/lti/launch`
   - Client ID: `gravlms_client_123`
   - Initiate Login URL: `https://videos.example.com/lti/login`
   - Public JWK: `{"kty":"RSA","e":"AQAB","kid":"key1","n":"..."}`

2. Register GravLMS in the video platform's admin panel using GravLMS endpoints
3. Register the video tool in GravLMS Admin → LTI Tools
4. Create a course titled "Video Lessons" and select the video tool
5. Assign to students
6. Students click "Video Lessons" and are securely launched into the video platform

---

## Provider Mode: Serving GravLMS to External Platforms

In this mode, GravLMS acts as a **Tool Provider** and serves its courses to external LMS platforms like Canvas, Moodle, or Blackboard.

### Use Case

You want to make your GravLMS courses available within another LMS platform (e.g., your institution uses Canvas, but you want to deliver specific courses from GravLMS).

### LTI 1.3 Provider Setup

#### 1. Register an External Platform in GravLMS

1. Log in to GravLMS as an admin
2. Navigate to the **Admin** panel
3. Go to the **LTI Platforms** tab
4. Click **Add Platform**
5. Fill in the following information from the external LMS:

   - **Issuer**: Platform issuer URL (e.g., `https://canvas.instructure.com`)
   - **Client ID**: Provided by the external LMS
   - **Auth Login URL**: OIDC login URL from the external LMS
   - **Auth Token URL**: OAuth 2.0 token URL from the external LMS
   - **Key Set URL**: JWKS URL from the external LMS
   - **Deployment ID**: Deployment identifier (optional)

6. Click **Save**

#### 2. Register GravLMS in the External LMS

In the external LMS (e.g., Canvas, Moodle), register GravLMS as an external tool:

**Configuration Type**: Manual Entry or Paste JSON

**Required Information**:

```
Tool Name:           GravLMS
OIDC Login URL:      http://localhost:8080/api/lti/login
Launch URL:          http://localhost:8080/api/lti/launch
JWKS URL:            http://localhost:8080/api/lti/jwks
```

**Additional Settings**:
- Privacy Level: Public (to send user information)
- Placements: Course Navigation, Assignment Selection (depending on use case)

> **Note**: Replace `localhost:8080` with your actual domain in production.

#### 3. Launch GravLMS from the External LMS

1. In the external LMS, add GravLMS as an external tool to a course
2. Students click on the GravLMS link within the external LMS
3. The external LMS sends an LTI launch request to GravLMS
4. GravLMS validates the request and displays the appropriate course content
5. Students can complete lessons and tests within GravLMS
6. Completion data can be sent back to the external LMS (if configured)

### What Happens During Provider Mode Launch

1. **External LMS initiates launch**: Sends OIDC login request to GravLMS
2. **GravLMS validates platform**: Checks if the platform is registered
3. **GravLMS processes launch**: Extracts user and context information
4. **GravLMS creates/logs in user**: Automatically creates a user account if needed
5. **GravLMS displays content**: Shows the appropriate course or dashboard
6. **User interacts with GravLMS**: Completes lessons, takes tests, etc.
7. **Optional: Grade passback**: GravLMS can send completion/grade data back to the external LMS

### Example: Serving GravLMS Courses in Canvas

**Scenario**: Your institution uses Canvas, but you want to deliver a specialized course from GravLMS.

1. In Canvas, go to **Settings** → **Apps** → **View App Configurations**
2. Click **+ App** and select **Manual Entry**
3. Enter:
   - Name: `GravLMS`
   - Consumer Key: (not needed for LTI 1.3)
   - Shared Secret: (not needed for LTI 1.3)
   - OIDC Login URL: `http://localhost:8080/api/lti/login`
   - Launch URL: `http://localhost:8080/api/lti/launch`
   - JWKS URL: `http://localhost:8080/api/lti/jwks`

4. In GravLMS Admin, register Canvas as a platform using Canvas's issuer and URLs
5. In Canvas, add GravLMS to a course module
6. Students click the GravLMS link and are launched into your GravLMS course

---

## Testing with Real Platforms

### Testing Consumer Mode with a Test LTI Tool

Use the **IMS Global LTI Tool Provider Simulator**:

1. Visit: https://lti.tools/saltire/tp
2. Note the Launch URL, Consumer Key, and Shared Secret
3. Register this tool in GravLMS
4. Create a course and test the launch

### Testing Provider Mode with Canvas Free for Teachers

1. Sign up for a free Canvas account: https://canvas.instructure.com/register
2. Create a course
3. Register GravLMS as an external tool (see Provider Mode setup above)
4. Add GravLMS to a module
5. Test the launch as a student

### Testing Provider Mode with Moodle Sandbox

1. Use a Moodle sandbox: https://sandbox.moodledemo.net/
2. Log in as admin
3. Go to **Site Administration** → **Plugins** → **Activity Modules** → **External Tool** → **Manage Tools**
4. Add GravLMS as a tool
5. Add to a course and test

---

## Troubleshooting

### Common Issues

#### "Invalid OAuth Signature" (LTI 1.1)

**Cause**: Mismatch between Consumer Key/Shared Secret or timestamp issues.

**Solution**:
- Verify Consumer Key and Shared Secret match exactly
- Check server time synchronization (OAuth 1.0 is sensitive to time drift)
- Ensure the Tool URL is correct

#### "Invalid JWT" (LTI 1.3)

**Cause**: Token validation failed.

**Solution**:
- Verify Client ID matches
- Check that JWKS URL is accessible
- Ensure public key is correctly configured
- Check token expiration time

#### "Platform Not Registered"

**Cause**: The external LMS trying to launch GravLMS is not registered.

**Solution**:
- Register the platform in GravLMS Admin → LTI Platforms
- Ensure the Issuer URL matches exactly

#### "User Not Found" or "Access Denied"

**Cause**: User information not passed correctly or user not assigned to course.

**Solution**:
- Check privacy settings in the external LMS (must send user data)
- Verify user is assigned to the course in GravLMS
- Check LTI role mapping

### Debugging Tips

1. **Check Browser Console**: Look for JavaScript errors or network failures
2. **Check PHP Error Logs**: 
   ```bash
   docker compose logs web
   ```
3. **Verify Database**: Check if LTI tools/platforms are correctly stored
4. **Test with Postman**: Manually craft LTI launch requests to test endpoints
5. **Enable Debug Mode**: Add logging to LTI endpoints to see incoming requests

### Security Considerations

- **Use HTTPS in Production**: LTI requires HTTPS for security
- **Rotate Keys Regularly**: Update shared secrets and key pairs periodically
- **Validate All Inputs**: Never trust incoming LTI data without validation
- **Use Nonce Checking**: Prevent replay attacks (already implemented in GravLMS)
- **Limit Token Expiration**: Keep JWT expiration times short (5-10 minutes)

---

## Advanced Features

### Custom Launch Parameters

You can pass custom parameters to external tools by modifying the launch request in the dashboard component.

**Example**: Pass a custom parameter to an LTI 1.1 tool

```typescript
// In dashboard.ts
const formData = {
  // ... standard LTI parameters
  custom_course_code: 'MATH101',
  custom_semester: 'Fall2024'
};
```

### Deep Linking (LTI 1.3)

Deep linking allows external tools to return content selections back to GravLMS. This feature can be implemented by:

1. Adding deep linking support to the LTI launch flow
2. Handling the content item return message
3. Storing the selected content in GravLMS

### Grade Passback

GravLMS can send completion and grade data back to external LMS platforms using LTI Assignment and Grade Services (AGS).

**Implementation Steps**:
1. Store the `lineitem` URL from the LTI launch
2. When a user completes a course, send a score to the lineitem URL
3. Use OAuth 2.0 to authenticate the grade passback request

---

## Summary

GravLMS supports comprehensive LTI integration:

| Mode | LTI Version | Use Case | Setup Complexity |
|------|-------------|----------|------------------|
| Consumer | 1.1 | Launch simple external tools | Low |
| Consumer | 1.3 | Launch modern external tools | Medium |
| Provider | 1.3 | Serve GravLMS to external LMS | Medium |

**Next Steps**:
1. Decide which mode you need (Consumer or Provider)
2. Follow the setup steps for your chosen mode
3. Test with a sandbox platform before production
4. Configure HTTPS and proper domain names for production use

For additional support, consult the IMS Global LTI specification: https://www.imsglobal.org/activity/learning-tools-interoperability
