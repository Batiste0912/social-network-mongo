const { MongoClient } = require('mongodb');

// MongoDB connection URL - update this with your MongoDB connection string
const MONGO_URL = process.env.MONGO_URL || 'mongodb://localhost:27017';
const DB_NAME = process.env.DB_NAME || 'social_network';

async function migrate() {
  const client = new MongoClient(MONGO_URL);

  try {
    console.log('Connecting to MongoDB...');
    await client.connect();
    console.log('Connected successfully to MongoDB');

    const db = client.db(DB_NAME);

    // Create collections
    console.log('\n=== Creating Collections ===');

    // Users collection
    try {
      await db.createCollection('users', {
        validator: {
          $jsonSchema: {
            bsonType: 'object',
            required: ['pseudo', 'email', 'createdAt'],
            properties: {
              pseudo: {
                bsonType: 'string',
                description: 'User pseudonym - required'
              },
              email: {
                bsonType: 'string',
                pattern: '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$',
                description: 'User email - required and must be valid'
              },
              bio: {
                bsonType: 'string',
                description: 'User biography - optional'
              },
              createdAt: {
                bsonType: 'date',
                description: 'Account creation date - required'
              }
            }
          }
        }
      });
      console.log('✓ Users collection created');
    } catch (error) {
      if (error.code === 48) {
        console.log('✓ Users collection already exists');
      } else {
        throw error;
      }
    }

    // Categories collection
    try {
      await db.createCollection('categories', {
        validator: {
          $jsonSchema: {
            bsonType: 'object',
            required: ['name', 'createdAt'],
            properties: {
              name: {
                bsonType: 'string',
                description: 'Category name - required'
              },
              description: {
                bsonType: 'string',
                description: 'Category description - optional'
              },
              createdAt: {
                bsonType: 'date',
                description: 'Category creation date - required'
              }
            }
          }
        }
      });
      console.log('✓ Categories collection created');
    } catch (error) {
      if (error.code === 48) {
        console.log('✓ Categories collection already exists');
      } else {
        throw error;
      }
    }

    // Posts collection
    try {
      await db.createCollection('posts', {
        validator: {
          $jsonSchema: {
            bsonType: 'object',
            required: ['userId', 'content', 'createdAt'],
            properties: {
              userId: {
                bsonType: 'objectId',
                description: 'User ID who created the post - required'
              },
              categoryId: {
                bsonType: 'objectId',
                description: 'Category ID of the post - optional'
              },
              content: {
                bsonType: 'string',
                description: 'Post content - required'
              },
              createdAt: {
                bsonType: 'date',
                description: 'Post creation date - required'
              },
              updatedAt: {
                bsonType: 'date',
                description: 'Post last update date - optional'
              }
            }
          }
        }
      });
      console.log('✓ Posts collection created');
    } catch (error) {
      if (error.code === 48) {
        console.log('✓ Posts collection already exists');
      } else {
        throw error;
      }
    }

    // Comments collection
    try {
      await db.createCollection('comments', {
        validator: {
          $jsonSchema: {
            bsonType: 'object',
            required: ['postId', 'userId', 'content', 'createdAt'],
            properties: {
              postId: {
                bsonType: 'objectId',
                description: 'Post ID the comment belongs to - required'
              },
              userId: {
                bsonType: 'objectId',
                description: 'User ID who created the comment - required'
              },
              content: {
                bsonType: 'string',
                description: 'Comment content - required'
              },
              createdAt: {
                bsonType: 'date',
                description: 'Comment creation date - required'
              },
              updatedAt: {
                bsonType: 'date',
                description: 'Comment last update date - optional'
              }
            }
          }
        }
      });
      console.log('✓ Comments collection created');
    } catch (error) {
      if (error.code === 48) {
        console.log('✓ Comments collection already exists');
      } else {
        throw error;
      }
    }

    // Likes collection
    try {
      await db.createCollection('likes', {
        validator: {
          $jsonSchema: {
            bsonType: 'object',
            required: ['postId', 'userId', 'createdAt'],
            properties: {
              postId: {
                bsonType: 'objectId',
                description: 'Post ID that was liked - required'
              },
              userId: {
                bsonType: 'objectId',
                description: 'User ID who liked the post - required'
              },
              createdAt: {
                bsonType: 'date',
                description: 'Like creation date - required'
              }
            }
          }
        }
      });
      console.log('✓ Likes collection created');
    } catch (error) {
      if (error.code === 48) {
        console.log('✓ Likes collection already exists');
      } else {
        throw error;
      }
    }

    // Follows collection
    try {
      await db.createCollection('follows', {
        validator: {
          $jsonSchema: {
            bsonType: 'object',
            required: ['followerId', 'followedId', 'createdAt'],
            properties: {
              followerId: {
                bsonType: 'objectId',
                description: 'User ID who follows - required'
              },
              followedId: {
                bsonType: 'objectId',
                description: 'User ID being followed - required'
              },
              createdAt: {
                bsonType: 'date',
                description: 'Follow relationship creation date - required'
              }
            }
          }
        }
      });
      console.log('✓ Follows collection created');
    } catch (error) {
      if (error.code === 48) {
        console.log('✓ Follows collection already exists');
      } else {
        throw error;
      }
    }

    // Create indexes for performance
    console.log('\n=== Creating Indexes ===');

    // Users indexes
    await db.collection('users').createIndex({ email: 1 }, { unique: true });
    console.log('✓ Users: unique index on email');

    await db.collection('users').createIndex({ pseudo: 1 });
    console.log('✓ Users: index on pseudo');

    // Categories indexes
    await db.collection('categories').createIndex({ name: 1 }, { unique: true });
    console.log('✓ Categories: unique index on name');

    // Posts indexes
    await db.collection('posts').createIndex({ userId: 1 });
    console.log('✓ Posts: index on userId');

    await db.collection('posts').createIndex({ categoryId: 1 });
    console.log('✓ Posts: index on categoryId');

    await db.collection('posts').createIndex({ createdAt: -1 });
    console.log('✓ Posts: index on createdAt (descending)');

    // Comments indexes
    await db.collection('comments').createIndex({ postId: 1 });
    console.log('✓ Comments: index on postId');

    await db.collection('comments').createIndex({ userId: 1 });
    console.log('✓ Comments: index on userId');

    await db.collection('comments').createIndex({ createdAt: -1 });
    console.log('✓ Comments: index on createdAt (descending)');

    // Likes indexes
    await db.collection('likes').createIndex({ postId: 1, userId: 1 }, { unique: true });
    console.log('✓ Likes: compound unique index on postId and userId');

    await db.collection('likes').createIndex({ userId: 1 });
    console.log('✓ Likes: index on userId');

    // Follows indexes
    await db.collection('follows').createIndex({ followerId: 1, followedId: 1 }, { unique: true });
    console.log('✓ Follows: compound unique index on followerId and followedId');

    await db.collection('follows').createIndex({ followedId: 1 });
    console.log('✓ Follows: index on followedId');

    console.log('\n=== Migration completed successfully! ===');
    console.log(`Database: ${DB_NAME}`);
    console.log('Collections created: users, categories, posts, comments, likes, follows');
    console.log('Indexes created for optimal performance');

  } catch (error) {
    console.error('Migration failed:', error);
    process.exit(1);
  } finally {
    await client.close();
    console.log('\nConnection closed');
  }
}

// Run migration
migrate();
