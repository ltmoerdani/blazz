const crypto = require('crypto');

function generateSignature(bodyString, timestamp, secret) {
  return crypto.createHmac('sha256', secret).update(bodyString + String(timestamp)).digest('hex');
}

function constantTimeEquals(a, b) {
  const buffA = Buffer.from(a);
  const buffB = Buffer.from(b);
  if (buffA.length !== buffB.length) return false;
  return crypto.timingSafeEqual(buffA, buffB);
}

module.exports = {
  generateSignature,
  constantTimeEquals
};
